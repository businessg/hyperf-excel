<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Command;

use BusinessG\BaseExcel\Console\ImportCommandHandler;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ImportCommand extends HyperfCommand
{
    public function __construct(protected ImportCommandHandler $handler)
    {
        parent::__construct('excel:import');
    }

    public function handle(): int
    {
        $result = $this->handler->handle(
            $this->input->getArgument('config'),
            $this->input->getArgument('path'),
            $this->input->getOption('progress'),
            $this->output
        );
        return $result['exitCode'];
    }

    protected function configure(): void
    {
        $this->setDescription('Run import');
        $this->addArgument('config', InputArgument::REQUIRED, 'The config of import.');
        $this->addArgument('path', InputArgument::REQUIRED, 'The file path of import.');
        $this->addOption('progress', 'g', InputOption::VALUE_NEGATABLE, 'The progress path of import.', true);
        $this->addUsage('excel:import "App\Excel\DemoImportConfig" "https://xxx.com/demo.xlsx"');
        $this->addUsage('excel:import "App\Excel\DemoImportConfig" "/excel/demo.xlsx"');
        $this->addUsage('excel:import "App\Excel\DemoImportConfig" "/excel/demo.xlsx" --no-progress');
    }
}