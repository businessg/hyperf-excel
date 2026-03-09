<?php

namespace Vartruexuan\HyperfExcel;

use BusinessG\BaseExcel\ExcelInvoker as BaseExcelInvoker;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Vartruexuan\HyperfExcel\Driver\DriverFactory;

class ExcelInvoker extends BaseExcelInvoker
{
    protected function getDefaultDriverName(ContainerInterface $container): string
    {
        return (string) $container->get(ConfigInterface::class)->get('excel.default', 'xlswriter');
    }

    protected function getDriverFactoryClass(): string
    {
        return DriverFactory::class;
    }
}