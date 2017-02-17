<?php
/**
 * Created by PhpStorm.
 * User: tyler
 * Date: 2/17/17
 * Time: 8:52 AM
 */

namespace TinyPNG\Console\Commands\ApiKey;


use Html2Text\Html2Text;
use GuzzleHttp\Client;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Respect\Validation\Validator as Validator;

class GenerateCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('api-key:generate')
            ->setDescription('Request an API Key.')
            ->addArgument('email', InputArgument::OPTIONAL, 'Your API key will be emailed here')
            ->addArgument('name', InputArgument::OPTIONAL, 'Your full name [<comment>"James Dean"</comment>]');
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
        $email = $this->getEmailRequired($input, $output);
        $userFullName = $this->getNameRequired($input, $output);
        $client = $this->setGuzzleBaseClient();
        $keyRequest = $client->post('/developers/subscription/new', [
                'form_params' => [
                    'fullName' => $userFullName,
                    'mail' => $email
                ]
            ]
        );
        $tinyPngResponse = '<comment>TinyPNG Response:</comment> '.@Html2Text::convert($keyRequest->getBody());
        $output->writeln($tinyPngResponse);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string
     */
    protected function getEmailRequired(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');
        $emailValidator = Validator::email();
        while (!$emailValidator->validate($email)):
            /** @var Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $emailPrompt = new Question('(ctrl+c to abort) <comment>Where should the API key be emailed? </comment>');
            $email = $helper->ask($input, $output, $emailPrompt);
        endwhile;

        return $email;
    }
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string
     */
    protected function getNameRequired(InputInterface $input, OutputInterface $output)
    {
        $fullName = $input->getArgument('name');
        $nameValidator = Validator::stringType()->length(1,32);
        while (!$nameValidator->validate($fullName)):
            /** @var Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $namePrompt = new Question('(ctrl+c to abort) <comment>What name should we associated with your TinyPNG API account?</comment> ');
            $fullName = $helper->ask($input, $output, $namePrompt);
        endwhile;

        return $fullName;
    }

    protected function setGuzzleBaseClient()
    {
        $client = new Client([
            // Base URI is used with relative requests
            'base_uri' => 'https://tinypng.com',
            'headers' => [
                'x-requested-with' => 'XMLHttpRequest'
            ]
        ]);

        return $client;
    }
}