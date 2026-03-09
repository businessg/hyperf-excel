<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Command;

use BusinessG\BaseExcel\Console\ProgressCommandHandler;
use Hyperf\Command\Command as HyperfCommand;
use Symfony\Component\Console\Input\InputArgument;

class ProgressCommand extends HyperfCommand
{
    public function __construct(protected ProgressCommandHandler $handler)
    {
        parent::__construct('excel:progress');
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
        $this->setDescription('View progress information');
        $this->addArgument('token', InputArgument::REQUIRED, 'The token of excel.');
        $this->addUsage('excel:progress 168d8baf7fbc435c8ef18239e932b101');
    }
}