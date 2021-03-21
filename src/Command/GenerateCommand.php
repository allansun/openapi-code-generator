<?php

namespace OpenAPI\CodeGenerator\Command;


use OpenAPI\CodeGenerator\Code\CodeGeneratorInterface;
use OpenAPI\CodeGenerator\Code\V3\CodeGenerator;
use OpenAPI\CodeGenerator\Config;
use OpenAPI\CodeGenerator\Logger;
use OpenAPI\Parser;
use OpenAPI\Schema\V2\Swagger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class GenerateCommand extends Command
{
    protected function configure()
    {
        $this->setName('generate')
            ->setDescription('Generates code from swagger')
            ->addOption('input', 'f', InputOption::VALUE_OPTIONAL,
                'Input Swagger or OpenAPI spec file, or a directory contains such files',
                './openapi/bs_v3-client.json'
            )
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL,
                'Config file (php format) to control how the code generator should work', null);

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Logger::getInstance()->setLogger(new ConsoleLogger($output));
        $configFile = $input->getOption('config');
        if (null !== $configFile) {
            /** @noinspection PhpIncludeInspection */
            $options = require_once $configFile;
        } else {
            $options = [];
        }
        $config = Config::getInstance($options);

        $finder = $this->getFinder($input);

        foreach ($finder as $file) {
            Logger::getInstance()->debug('Parsing ' . $file);
            $spec = Parser::parse($file->getRealPath());

            $this->generate($config, $spec);
        }
        return 0;
    }

    private function getFinder(InputInterface $input): Finder
    {
        $finder    = new Finder();
        $inputFile = $input->getOption('input');
        if (is_dir($inputFile)) {
            $finder->in($inputFile)->files()->name(['*.json', '*.yml', '*.yaml']);
        } else {
            $fileInfo = explode(DIRECTORY_SEPARATOR, $inputFile);
            $filename = array_pop($fileInfo);
            $filePath = implode(DIRECTORY_SEPARATOR, $fileInfo);
            $finder->in(getcwd() . DIRECTORY_SEPARATOR . $filePath)->files()->name($filename);
        }

        return $finder;
    }

    private function generate(Config $config, $spec): void
    {

        $codeGeneratorClass = $config->getOption(Config::OPTION_CODE_GENERATOR_CLASS);

        if (null === $codeGeneratorClass) {
            if ($spec instanceof Swagger) {
                $codeGeneratorClass = CodeGenerator::class;
            } else {
                $codeGeneratorClass = CodeGenerator::class;
            }
        }

        /** @var CodeGeneratorInterface $CodeGenerator */
        $CodeGenerator = new $codeGeneratorClass($spec);

        $CodeGenerator->generateModels();
        $CodeGenerator->generateApis();
        $CodeGenerator->generateResponseTypes();

    }

}