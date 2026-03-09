<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Progress;

use BusinessG\BaseExcel\Progress\Progress;
use BusinessG\BaseExcel\Progress\ProgressInterface;
use BusinessG\BaseExcel\Progress\ProgressStorageInterface;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class ProgressFactory
{
    public function __invoke(ContainerInterface $container): ProgressInterface
    {
        $config = $container->get(ConfigInterface::class)->get('excel.progress', [
            'enable' => true,
            'prefix' => 'HyperfExcel',
            'expire' => 3600,
        ]);
        $storage = $container->get(ProgressStorageInterface::class);

        return new Progress($storage, $config);
    }
}
