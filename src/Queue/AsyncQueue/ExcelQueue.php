<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Queue\AsyncQueue;

use Hyperf\AsyncQueue\Driver\DriverInterface;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use BusinessG\BaseExcel\Data\BaseConfig;
use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\HyperfExcel\Queue\AsyncQueue\Job\ExportJob;
use BusinessG\HyperfExcel\Queue\AsyncQueue\Job\ImportJob;
use BusinessG\BaseExcel\Queue\ExcelQueueInterface;
use Hyperf\AsyncQueue\Driver\DriverFactory;

class ExcelQueue implements ExcelQueueInterface
{
    public DriverInterface $queue;

    protected array $config;

    public function __construct(protected ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $this->config = $config->get('excel.queue', []);
        $this->queue = $this->container->get(DriverFactory::class)->get($this->config['name'] ?? 'default');
    }

    public function push(BaseConfig $config): void
    {
        $job = $config instanceof ExportConfig ? ExportJob::class : ImportJob::class;
        $this->queue->push(new $job($config));
    }

}