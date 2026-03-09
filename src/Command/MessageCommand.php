<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Command;

use BusinessG\BaseExcel\Console\MessageCommandHandler;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MessageCommand extends HyperfCommand
{
    public function __construct(protected MessageCommandHandler $handler)
    {
        parent::__construct('excel:message');
    }

    public function handle(): int
    {
        return $this->handler->handle(
            $this->input->getArgument('token'),
            (int) $this->input->getOption('num'),
            $this->input->getOption('progress'),
            $this->output
        );
    }

    protected function configure(): void
    {
        $this->setDescription('View progress messages');
        $this->addArgument('token', InputArgument::REQUIRED, 'The token of excel.');
        $this->addOption('num', 'c', InputOption::VALUE_REQUIRED, 'The message num of excel.', 50);
        $this->addOption('progress', 'g', InputOption::VALUE_NEGATABLE, 'The progress of export.', true);
        $this->addUsage('excel:message 168d8baf7fbc435c8ef18239e932b101');
        $this->addUsage('excel:message 168d8baf7fbc435c8ef18239e932b101 --no-progress');
    }
}