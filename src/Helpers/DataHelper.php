<?php

namespace TinyPNG\Console\Helpers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class DataHelper
{

    private static $_APP_CONFIG_DIR = ".tinypngconsole";
    private static $_APP_CONFIG_FILENAME = ".config";
    public static $CONFIG_INDEX_API_KEY = 'key';

    public function __construct(InputInterface $input, OutputInterface $output)
    {
    }

    static function getApiKey()
    {
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists(DataHelper::getConfigFilePath())):
            return "";
        else:
            return @DataHelper::getParsedConfig()[self::$CONFIG_INDEX_API_KEY];
        endif;
    }

    /**
     * @return string
     */
    static function getConfigFilePath(): string
    {
        return DataHelper::getConfigDir() . DIRECTORY_SEPARATOR . DataHelper::$_APP_CONFIG_FILENAME;
    }

    /**
     * @return string
     */
    static function getConfigDir()
    {
        $configBaseDir = DataHelper::getUserAppDataBase();
        return $configBaseDir . DIRECTORY_SEPARATOR . DataHelper::$_APP_CONFIG_DIR;
    }

    /**
     * @throws \Exception
     * @return string
     */
    static function getUserAppDataBase(): string
    {
        $getConfigBaseDirCommand = DataHelper::getConfigBaseDirCommand();
        $configBaseDirProcess = new Process($getConfigBaseDirCommand);
        $configBaseDirProcess->run();
        // executes after the command finishes
        if (!$configBaseDirProcess->isSuccessful()) {
            $errorMsg = sprintf('We attempted to locate your base config directory using `%s` but the process did not complete successfully.', $getConfigBaseDirCommand);
            throw new \Exception($errorMsg);
        }

        $configBaseDir = trim($configBaseDirProcess->getOutput());
        return $configBaseDir;
    }

    /**
     * @return string
     */
    static function getConfigBaseDirCommand(): string
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

    static function getParsedConfig()
    {
        $configFilePath = DataHelper::getConfigFilePath();
        if (DataHelper::userHasConfigFile()):
            $configRaw = file_get_contents($configFilePath);
            $config = new Yaml();
            try {
                $parsedConfig = @$config::parse($configRaw);
            } catch (ParseException $e) {
                throw new \Exception(sprintf("Unable to parse the YAML string: %s in %s. Consider backing up and deleting %s", $e->getMessage(), $configFilePath, $configFilePath));
            }
        else:
            $parsedConfig = Yaml::parse("");
        endif;

        if (!is_array($parsedConfig)):
            DataHelper::removeFile($configFilePath);
            return $parsedConfig = [];
        endif;

        return $parsedConfig;
    }

    /**
     * @return bool
     */
    static function userHasConfigFile()
    {
        DataHelper::establishConfigDir();
        $finder = new Finder();
        $configFile = $finder
            ->files()
            ->ignoreDotFiles(false)
            ->name(DataHelper::$_APP_CONFIG_FILENAME)
            ->in(DataHelper::getConfigDir());
        return ($configFile->count() > 0);
    }

    static function establishConfigDir()
    {
        $finder = new Finder();
        $configDir = $finder
            ->directories()
            ->ignoreDotFiles(false)
            ->name(DataHelper::$_APP_CONFIG_DIR)
            ->in(DataHelper::getUserAppDataBase())
            ->depth('== 0');
        $configDirExists = ($configDir->count() > 0);
        if (!$configDirExists):
            DataHelper::createConfigDir();
        endif;
    }

    static function createConfigDir()
    {
        $fileSystem = new Filesystem();
        $fileSystem->mkdir(DataHelper::getConfigDir(), 0700);
    }
    /**
     * @param $filePath
     */
    static function removeFile($filePath)
    {
        $filesystem = new Filesystem();
        $filesystem->remove($filePath);
    }

}