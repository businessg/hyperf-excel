<?php

namespace Vartruexuan\HyperfExcel;

use BusinessG\BaseExcel\AbstractExcel;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Vartruexuan\HyperfExcel\Driver\DriverFactory;

class Excel extends AbstractExcel implements \BusinessG\BaseExcel\ExcelInterface
{
    protected function resolveConfig(): array
    {
        return $this->container->get(ConfigInterface::class)->get('excel', []);
    }

    protected function resolveEventDispatcher(): object
    {
        return $this->container->get(EventDispatcherInterface::class);
    }

    protected function getDriverFactoryClass(): string
    {
        return DriverFactory::class;
    }
}
