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
            ->addOption('recursive-depth', 'd', InputOption::VALUE_OPTIONAL, "How many subdirectory levels should this command search for your file?", 0)
            ->addOption('seo', 's', InputOption::VALUE_OPTIONAL, "Replace spaces in filename with hyphens.", 0)
            ->addOption('move', 'm', InputOption::VALUE_OPTIONAL, "Move file to new name. Considered if '--seo=1'", 0)
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
        $output->writeln(["", "<info>Done!</info>", ""]);
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
        $additionalOutput = "";
        if ($fileSearch->count() > 0):
            foreach ($fileSearch as $fileResult):
                $isAllowedImageType = in_array($fileResult->getExtension(), $this->allowed_extensions, false);
                if (!$isAllowedImageType):
                    continue;
                endif;
                if ($this->noRequestMove($input, $fileResult)) {
                    $output->writeln("Cannot write to file. Skipping {$fileResult->getFilename()}");
                    continue;
                }
                $savePath = $fileResult->getRealPath();
                if ($input->getOption('seo') == 1) {
                    $fileName = $this->strToSeo($fileResult->getFilename());
                    $savePath = $fileResult->getPath() . DIRECTORY_SEPARATOR . $fileName;
                }

                $output->writeln([
                    '',
                    sprintf(" - <comment>Uploading to TinyPNG: `%s` (%s MB)</comment>",
                        $fileResult->getRealPath(),
                        $this->bytesToMb($fileResult->getSize())
                    )
                ]);

                $sourceImage = $this->uploadImage($fileResult);

                $output->writeln(sprintf(" - <comment>Compressing `%s`</comment>", $fileResult->getRealPath()));

                $sourceImage->toFile($savePath);

                if ($this->isMoveRequest($input) && $fileResult->getRealPath() !== $savePath) {
                    $originalPath = $fileResult->getRealPath();
                    unlink($originalPath);
                    $additionalOutput = "     Moved from: {$originalPath}";
                } elseif ($this->isMoveRequest($input)) {
                    $additionalOutput = "     Filename was already SEO friendly.";
                }

                $compressedSize = $sourceImage->result()->size();
                $bytesSaved = $fileResult->getSize() - $compressedSize;

                $output->writeln([
                    sprintf("     <info>Saved to: `%s`</info> (%s MB | %s KB saved)", $savePath, $this->bytesToMb($compressedSize), $this->bytesToKb($bytesSaved)),
                    $additionalOutput
                ]);
            endforeach;
        endif;
    }

    /**
     * @param $string
     * @return string
     */
    protected function strToSeo($string): string
    {
        return (string)preg_replace('/\s+/', '-', $string);
    }

    /**
     * @param InputInterface $input
     * @param $fileResult
     * @return bool
     */
    protected function noRequestMove(InputInterface $input, $fileResult): bool
    {
        return $input->getOption('move') == 1 && !$fileResult->isWritable();
    }

    /**
     * @param InputInterface $input
     * @return bool
     */
    protected function isMoveRequest(InputInterface $input): bool
    {
        return $input->getOption('move') == 1;
    }

    /**
     * @param $bytes
     * @return float
     */
    protected function bytesToMb(int $bytes = 0, int $decimals = 3): float
    {
        $size = $bytes / 1024 / 1024;

        return number_format($size, $decimals);
    }

    /**
     * @param $fileResult
     * @return \Tinify\Source
     */
    protected function uploadImage($fileResult): \Tinify\Source
    {
        return \Tinify\fromFile($fileResult->getRealPath());
    }

    /**
     * @param $bytesSaved
     * @return float|int
     */
    protected function bytesToKb(int $bytesSaved = 0, int $decimals = 3)
    {
        return number_format($bytesSaved / 1024, $decimals);
    }
}