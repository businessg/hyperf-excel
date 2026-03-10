<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Queue\AsyncQueue\Job;

use BusinessG\BaseExcel\Data\BaseConfig;
use BusinessG\BaseExcel\Queue\ExcelJobTrait;
use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerInterface;

abstract class BaseJob extends Job
{
    use ExcelJobTrait;

    protected int $maxAttempts = 0;

    public function __construct(BaseConfig $config)
    {
        $this->config = $config;
    }

    protected function getContainer(): ContainerInterface
    {
        return ApplicationContext::getContainer();
    }

    public function fail(\Throwable $e): void
    {
        $this->dispatchError($e);
    }

    abstract public function handle(): void;
}