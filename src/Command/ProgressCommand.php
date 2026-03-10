<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Command;

use BusinessG\BaseExcel\Console\ProgressCommandHandler;
use Hyperf\Command\Command as HyperfCommand;

class ProgressCommand extends HyperfCommand
{
    public function __construct(protected ProgressCommandHandler $handler)
    {
        parent::__construct(ProgressCommandHandler::getCommandName());
    }

    public function handle(): int
    {
        return $this->handler->handle(
            $this->input->getArgument('token'),
            $this->output
        );
    }

    protected function configure(): void
    {
        ProgressCommandHandler::configureTo($this);
    }
}
