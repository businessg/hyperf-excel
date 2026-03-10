<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Command;

use BusinessG\BaseExcel\Console\ImportCommandHandler;
use Hyperf\Command\Command as HyperfCommand;

class ImportCommand extends HyperfCommand
{
    public function __construct(protected ImportCommandHandler $handler)
    {
        parent::__construct(ImportCommandHandler::getCommandName());
    }

    public function handle(): int
    {
        return $this->handler->handle(
            $this->input->getArgument('config'),
            $this->input->getArgument('path'),
            $this->input->getOption('progress'),
            $this->output
        )['exitCode'];
    }

    protected function configure(): void
    {
        ImportCommandHandler::configureTo($this);
    }
}
