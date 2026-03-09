<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Logger;

use BusinessG\BaseExcel\Logger\AbstractExcelLogger;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class ExcelLogger extends AbstractExcelLogger
{
    protected function resolveConfig(): array
    {
        return $this->container->get(ConfigInterface::class)->get('excel.logger', ['name' => 'hyperf-excel']);
    }

    protected function resolveLogger(): LoggerInterface
    {
        return $this->container->get(LoggerFactory::class)->get($this->config['name'] ?? 'hyperf-excel');
    }
}
