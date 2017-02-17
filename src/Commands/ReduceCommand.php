<?php
/**
 * Created by PhpStorm.
 * User: tyler
 * Date: 2/17/17
 * Time: 8:52 AM
 */

namespace TinyPNG\Console\Commands;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Tinify\Exception as TinifyException;
use TinyPNG\Console\Helpers\DataHelper;

class ReduceCommand extends Command
{
    /** @var string $apiKey */
    protected $apiKey;
    protected $allowed_extensions = ['png', 'jpg', 'jpeg'];

    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->apiKey = DataHelper::getApiKey();
    }

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('reduce')
            ->setDescription('"Optimize your images with a perfect balance in quality and file size." - TinyPNG')
            ->addArgument('fileName', InputArgument::REQUIRED, "Path to file needing optimization <comment>[accepts globs, strings, or regexes]</comment>")
            ->addOption('recursive-depth','d', InputOption::VALUE_OPTIONAL, "How many subdirectory levels should this command search for your file?",0)
            ->addOption('api-key', 'k', InputOption::VALUE_OPTIONAL, "API Key");
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface $input
     * @param  \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setRuntimeApiKey($input);

        $output->writeln(sprintf("<info>Searching for files with name like `%s` in %s</info>", $input->getArgument('fileName'), getcwd()));
        $fileSearch = $this->getTargetImages($input);
        $this->compressFiles($input, $output, $fileSearch);
        $output->writeln(["","<info>Done!</info>",""]);
    }

    /**
     * @param InputInterface $input
     */
    protected function setRuntimeApiKey(InputInterface $input)
    {
        $this->establishApiKey($input);
        try {
            \Tinify\setKey($this->apiKey);
            \Tinify\validate();
        } catch (TinifyException $e) {
            throw new RuntimeException(sprintf('TinyPNG Error: %s', $e->getMessage()));
        }
    }

    /**
     * @param InputInterface $input
     */
    protected function establishApiKey(InputInterface $input)
    {
        if ($input->getOption('api-key')):
            $this->setApiKey($input->getOption('api-key'));
        elseif (!$this->apiKey):
            throw new RuntimeException(
                'Error: Missing API Key' . "\n" .
                'Execute this command with `--api-key` option or set it permanently using `tinypng config`'
            );
        endif;
    }

    /**
     * @param string $apiKey
     * @return ReduceCommand
     */
    public function setApiKey(string $apiKey): ReduceCommand
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * @param InputInterface $input
     * @return Finder
     */
    protected function getTargetImages(InputInterface $input): Finder
    {
        $fileSearch = new Finder();
        $fileSearch->files()
            ->name($input->getArgument("fileName"))
            ->in(getcwd())
            ->depth("<={$input->getOption('recursive-depth')}");
        return $fileSearch;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $fileSearch
     */
    protected function compressFiles(InputInterface $input, OutputInterface $output, Finder $fileSearch)
    {
        if ($fileSearch->count() > 0):
            foreach ($fileSearch as $fileResult):
                $isAllowedImageType = in_array($fileResult->getExtension(), $this->allowed_extensions, false);
                if (!$isAllowedImageType):
                    continue;
                endif;
                $output->writeln(sprintf(" - <comment>Loading `%s`</comment>", $fileResult->getRealPath()));
                $sourceImage = \Tinify\fromFile($fileResult->getRealPath());
                $output->writeln(sprintf(" - <comment>Compressing `%s`</comment>", $fileResult->getRealPath()));
                $sourceImage->toFile($fileResult->getRealPath());
                $output->writeln(sprintf("     <info>Completed: `%s`</info>", $fileResult->getRealPath()));
            endforeach;
        endif;
    }
}