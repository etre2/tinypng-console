<?php
/**
 * Created by PhpStorm.
 * User: tyler
 * Date: 2/17/17
 * Time: 8:52 AM
 */

namespace TinyPNG\Console\Commands\ApiKey;

use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use TinyPNG\Console\Helpers\DataHelper;

class ConfigureCommand extends Command
{
    private static $_APP_CONFIG_DIR = ".tinypngconsole";
    private static $_APP_CONFIG_FILENAME = ".config";

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('api-key:config')
            ->setDescription('Configure console for API use.');
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
        $configFilePath = $this->getConfigFilePath($output);
        $configFile = $configFilePath;
        $helper = $this->getHelper('question');

        $question = new Question('(ctrl+c to exit) <comment>What is your API key?</comment> ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $apiKey = null;
        while(!$apiKey):
        $apiKey = $helper->ask($input, $output, $question);
        endwhile;

        $parsedConfig = $this->initParsedConfig($input, $output, $configFilePath, $helper);

        $parsedConfig[DataHelper::$CONFIG_INDEX_API_KEY] = $apiKey;
        $newYamlConfig = Yaml::dump($parsedConfig);
        $filesystem = new Filesystem();
        $filesystem->dumpFile($configFile, $newYamlConfig);
        $output->writeln("<info>Key Saved</info>");
    }

    /**
     * @param OutputInterface $output
     * @return string
     */
    protected function getConfigFilePath(OutputInterface $output): string
    {
        return $this->getConfigDir($output) . DIRECTORY_SEPARATOR . $this::$_APP_CONFIG_FILENAME;
    }

    /**
     * @param OutputInterface $output
     * @return string
     */
    protected function getConfigDir(OutputInterface $output)
    {
        $configBaseDir = $this->getUserAppDataBase($output);
        return $configBaseDir . DIRECTORY_SEPARATOR . $this::$_APP_CONFIG_DIR;
    }

    /**
     * @param OutputInterface $output
     * @return string
     */
    protected function getUserAppDataBase(OutputInterface $output): string
    {
        $getConfigBaseDirCommand = $this->getConfigBaseDirCommand();
        $configBaseDirProcess = new Process($getConfigBaseDirCommand);
        $configBaseDirProcess->run();
        // executes after the command finishes
        if (!$configBaseDirProcess->isSuccessful()) {
            $errorMsg = sprintf('<error>We attempted to locate your base config directory using `%s` but the process did not complete successfully.</error>', $getConfigBaseDirCommand);
            $output->writeln([$errorMsg, "<info>Your API cannot be saved, however, you may execute add <comment>`-k [APIKEY]`</comment> when executing <comment>`tinypng reduce`</comment>.</info>"]);
            exit(1);
        }

        $configBaseDir = trim($configBaseDirProcess->getOutput());
        return $configBaseDir;
    }

    /**
     * @return string
     */
    protected function getConfigBaseDirCommand(): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            /** Is Windows OS */
            $homeDirectoryCommand = 'echo %APPDATA%';
        } else {
            /** Is Linux OS */
            $homeDirectoryCommand = 'echo $HOME';
        }
        return $homeDirectoryCommand;
    }

    protected function initParsedConfig($input, $output, $configFilePath, $helper)
    {
        if ($this->userHasConfigFile($output)):
            $configRaw = file_get_contents($configFilePath);
            $config = new Yaml();
            try {
                $parsedConfig = @$config::parse($configRaw);
            } catch (ParseException $e) {
                $output->writeln(sprintf("<error>Unable to parse the YAML string: %s in %s</error>", $e->getMessage(), $configFilePath));
                $question = new ConfirmationQuestion('<comment>Do you want to delete your old configuration?</comment> ', false);

                $replaceConfig = $helper->ask($input, $output, $question);
                if (!$replaceConfig) {
                    $output->writeln("<info>Configuration aborted.</info>");
                    return;
                }
                $this->removeFile($configFilePath);
                $parsedConfig = [];
            }
        else:
            $parsedConfig = Yaml::parse("");
        endif;

        if (!is_array($parsedConfig)):
            $this->removeFile($configFilePath);
            return $parsedConfig = [];
        endif;

        return $parsedConfig;
    }

    /**
     * @param OutputInterface $output
     * @return bool
     */
    protected function userHasConfigFile(OutputInterface $output)
    {
        $this->validateConfigDir($output);
        $finder = new Finder();
        $configFile = $finder
            ->files()
            ->ignoreDotFiles(false)
            ->name($this::$_APP_CONFIG_FILENAME)
            ->in($this->getConfigDir($output));
        return ($configFile->count() > 0);
    }

    /**
     * @param OutputInterface $output
     */
    protected function validateConfigDir(OutputInterface $output)
    {
        $finder = new Finder();
        $configDir = $finder
            ->directories()
            ->ignoreDotFiles(false)
            ->name($this::$_APP_CONFIG_DIR)
            ->in($this->getUserAppDataBase($output))
            ->depth('== 0');
        $configDirExists = ($configDir->count() > 0);
        if (!$configDirExists):
            $this->createConfigDir($output);
        endif;
    }

    /**
     * @param OutputInterface $output
     */
    protected function createConfigDir(OutputInterface $output)
    {
        $fileSystem = new Filesystem();
        $fileSystem->mkdir($this->getConfigDir($output), 0700);
    }

    /**
     * @param $configFilePath
     */
    protected function removeFile($configFilePath)
    {
        $filesystem = new Filesystem();
        $filesystem->remove($configFilePath);
    }

    /**
     * [Credit for this method is to Taylor Otwell. Borrowed from laravel/installer]
     *
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }
        return 'composer';
    }
}