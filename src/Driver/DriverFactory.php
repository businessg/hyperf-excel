<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Driver;

use BusinessG\BaseExcel\Driver\AbstractDriverFactory;
use BusinessG\BaseExcel\Driver\DriverInterface;
use Hyperf\Contract\ConfigInterface;

use function Hyperf\Support\make;

class DriverFactory extends AbstractDriverFactory
{
    protected function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->container->get(ConfigInterface::class)->get($key, $default);
    }

    protected function makeDriver(string $class, array $params): DriverInterface
    {
        return make($class, $params);
    }
}
