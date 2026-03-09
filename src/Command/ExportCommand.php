<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Command;

use BusinessG\BaseExcel\Console\ExportCommandHandler;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ExportCommand extends HyperfCommand
{
    public function __construct(protected ExportCommandHandler $handler)
    {
        parent::__construct('excel:export');
    }

    public function handle(): int
    {
        $result = $this->handler->handle(
            $this->input->getArgument('config'),
            $this->input->getOption('progress'),
            $this->output
        );
        return $result['exitCode'];
    }

    protected function configure(): void
    {
        $this->setDescription('Run export');
        $this->addArgument('config', InputArgument::REQUIRED, 'The config of export.');
        $this->addOption('progress', 'g', InputOption::VALUE_NEGATABLE, 'The progress of export.', true);
        $this->addUsage('excel:export "App\Excel\DemoExportConfig"');
        $this->addUsage('excel:export "App\Excel\DemoExportConfig" --no-progress');
    }
}
