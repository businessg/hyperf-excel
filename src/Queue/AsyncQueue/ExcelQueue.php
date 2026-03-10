<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Queue\AsyncQueue;

use BusinessG\BaseExcel\Config\ExcelConfig;
use BusinessG\BaseExcel\Data\BaseConfig;
use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\BaseExcel\Queue\ExcelQueueInterface;
use BusinessG\HyperfExcel\Queue\AsyncQueue\Job\ExportJob;
use BusinessG\HyperfExcel\Queue\AsyncQueue\Job\ImportJob;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\AsyncQueue\Driver\DriverInterface;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class ExcelQueue implements ExcelQueueInterface
{
    public DriverInterface $queue;

    protected ExcelConfig $excelConfig;

    public function __construct(protected ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $this->excelConfig = ExcelConfig::fromArray($config->get('excel', []));
        $this->queue = $this->container->get(DriverFactory::class)->get($this->excelConfig->queue->connection);
    }

    public function push(BaseConfig $config): void
    {
        $job = $config instanceof ExportConfig ? ExportJob::class : ImportJob::class;
        $this->queue->push(new $job($config));
    }
}