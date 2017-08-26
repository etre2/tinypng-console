<?php
/**
 * Created by PhpStorm.
 * User: tyler
 * Date: 2/17/17
 * Time: 8:52 AM
 */

namespace TinyPNG\Console\Commands\ApiKey;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RequestCommand extends Command
{

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('api-key:request')
            ->setDescription('Request an API Key @ https://tinypng.com/developers');
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
        $output->writeln(['', 'Request an API Key @ https://tinypng.com/developers']);
    }
}