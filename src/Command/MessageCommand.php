<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Command;

use BusinessG\BaseExcel\Console\MessageCommandHandler;
use Hyperf\Command\Command as HyperfCommand;

class MessageCommand extends HyperfCommand
{
    public function __construct(protected MessageCommandHandler $handler)
    {
        parent::__construct(MessageCommandHandler::getCommandName());
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
        MessageCommandHandler::configureTo($this);
    }
}
