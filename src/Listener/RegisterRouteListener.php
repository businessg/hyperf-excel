<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Listener;

use BusinessG\BaseExcel\Config\ExcelConfig;
use BusinessG\HyperfExcel\Http\Controller\ExcelController;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\HttpServer\Router\Router;
use Psr\Container\ContainerInterface;

#[Listener]
class RegisterRouteListener implements ListenerInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function listen(): array
    {
        return [BootApplication::class];
    }

    public function process(object $event): void
    {
        $config = $this->container->get(ConfigInterface::class);
        $httpConfig = ExcelConfig::fromArray($config->get('excel', []))->http;

        if (!$httpConfig->enabled) {
            return;
        }

        $this->container->get(DispatcherFactory::class);

        Router::addGroup($httpConfig->prefix, function () {
            Router::addGroup('/excel', function () {
                Router::get('/export', [ExcelController::class, 'export']);
                Router::post('/export', [ExcelController::class, 'export']);
                Router::post('/import', [ExcelController::class, 'import']);
                Router::get('/progress', [ExcelController::class, 'progress']);
                Router::get('/message', [ExcelController::class, 'message']);
                Router::get('/info', [ExcelController::class, 'info']);
                Router::post('/upload', [ExcelController::class, 'upload']);
            });
        }, ['middleware' => $httpConfig->middleware]);
    }
}
