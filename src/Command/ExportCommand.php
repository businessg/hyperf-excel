<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Command;

use BusinessG\BaseExcel\Console\ExportCommandHandler;
use Hyperf\Command\Command as HyperfCommand;

class ExportCommand extends HyperfCommand
{
    public function __construct(protected ExportCommandHandler $handler)
    {
        parent::__construct(ExportCommandHandler::getCommandName());
    }

    public function handle(): int
    {
        return $this->handler->handle(
            $this->input->getArgument('config'),
            $this->input->getOption('progress'),
            $this->output
        )['exitCode'];
    }

    protected function configure(): void
    {
        ExportCommandHandler::configureTo($this);
    }
}
