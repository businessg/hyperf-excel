<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Queue\AsyncQueue\Job;

class ImportJob extends BaseJob
{
    public function handle(): void
    {
        $this->getExcel()->import($this->config->setIsAsync(false));
    }
}