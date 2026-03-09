<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Driver;

use BusinessG\BaseExcel\Driver\DriverInterface;
use BusinessG\BaseExcel\Exception\InvalidDriverException;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\make;

class DriverFactory
{
    protected array $drivers = [];
    protected array $configs = [];

    public function __construct(protected ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);

        $options = $config->get('excel.options');
        $this->configs = $config->get('excel.drivers', []);

        foreach ($this->configs as $key => $item) {
            $item = array_merge($options ?? [], $item);
            $driverClass = $item['driver'];

            if (!class_exists($driverClass)) {
                throw new InvalidDriverException(sprintf('[Error] class %s is invalid.', $driverClass));
            }

            $driver = make($driverClass, ['config' => $item, 'name' => $key]);
            if (!$driver instanceof DriverInterface) {
                throw new InvalidDriverException(sprintf('[Error] class %s is not instanceof %s.', $driverClass, DriverInterface::class));
            }

            $this->drivers[$key] = $driver;
        }
    }

    public function __get($name): DriverInterface
    {
        return $this->get($name);
    }

    public function get(string $name): DriverInterface
    {
        $driver = $this->drivers[$name] ?? null;
        if (!$driver instanceof DriverInterface) {
            throw new InvalidDriverException(sprintf('[Error]  %s is a invalid driver.', $name));
        }

        return $driver;
    }

    public function getConfig($name): array
    {
        return $this->configs[$name] ?? [];
    }
}
