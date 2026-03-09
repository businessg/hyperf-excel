<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Logger;

use BusinessG\BaseExcel\Logger\ExcelLoggerInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ExcelLogger implements ExcelLoggerInterface
{
    protected LoggerInterface $logger;
    protected array $config;

    public function __construct(protected ContainerInterface $container)
    {
        $config = $this->container->get(ConfigInterface::class);
        $this->config = $config->get('excel.logger', [
            'name' => 'hyperf-excel',
        ]);
        $this->logger = $this->container->get(LoggerFactory::class)->get($this->config['name'] ?? 'hyperf-excel');
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}