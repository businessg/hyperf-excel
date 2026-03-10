<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel;

use BusinessG\BaseExcel\AbstractExcel;
use BusinessG\BaseExcel\Console\ExportCommandHandler;
use BusinessG\BaseExcel\Console\ImportCommandHandler;
use BusinessG\BaseExcel\Console\MessageCommandHandler;
use BusinessG\BaseExcel\Console\ProgressCommandHandler;
use BusinessG\BaseExcel\Console\ProgressDisplay;
use BusinessG\BaseExcel\Contract\ConfigResolverInterface;
use BusinessG\BaseExcel\Contract\DeferInterface;
use BusinessG\BaseExcel\Contract\FilesystemResolverInterface;
use BusinessG\BaseExcel\Contract\FrameworkBridgeInterface;
use BusinessG\BaseExcel\Contract\LoggerResolverInterface;
use BusinessG\BaseExcel\Contract\ObjectFactoryInterface;
use BusinessG\BaseExcel\Contract\RedisAdapterInterface;
use BusinessG\BaseExcel\Contract\RedisResolverInterface;
use BusinessG\BaseExcel\Contract\ResponseFactoryInterface;
use BusinessG\BaseExcel\Db\ExcelLogInterface;
use BusinessG\BaseExcel\Db\ExcelLogManager;
use BusinessG\BaseExcel\Driver\DriverFactory;
use BusinessG\BaseExcel\Driver\DriverInterface;
use BusinessG\BaseExcel\ExcelInterface;
use BusinessG\BaseExcel\ExcelInvoker;
use BusinessG\BaseExcel\Logger\ExcelLogger;
use BusinessG\BaseExcel\Logger\ExcelLoggerInterface;
use BusinessG\BaseExcel\Progress\Progress;
use BusinessG\BaseExcel\Progress\ProgressInterface;
use BusinessG\BaseExcel\Progress\ProgressStorageInterface;
use BusinessG\BaseExcel\Progress\Storage\BridgeProgressStorage;
use BusinessG\BaseExcel\Queue\ExcelQueueInterface;
use BusinessG\BaseExcel\Strategy\Path\DateTimeExportPathStrategy;
use BusinessG\BaseExcel\Strategy\Path\ExportPathStrategyInterface;
use BusinessG\BaseExcel\Strategy\Token\TokenStrategyInterface;
use BusinessG\BaseExcel\Strategy\Token\UuidStrategy;
use Psr\Container\ContainerInterface;
use Vartruexuan\HyperfExcel\Command\ExportCommand;
use Vartruexuan\HyperfExcel\Command\ImportCommand;
use Vartruexuan\HyperfExcel\Command\MessageCommand;
use Vartruexuan\HyperfExcel\Command\ProgressCommand;
use Vartruexuan\HyperfExcel\Listener\HyperfExcelLogDbListener;
use Vartruexuan\HyperfExcel\Listener\HyperfProgressListener;
use Vartruexuan\HyperfExcel\Process\CleanFileProcess;
use Vartruexuan\HyperfExcel\Queue\AsyncQueue\ExcelQueue;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                FrameworkBridgeInterface::class => static fn (ContainerInterface $c) => new HyperfBridge($c),
                ConfigResolverInterface::class => static fn (ContainerInterface $c) => $c->get(FrameworkBridgeInterface::class),
                ObjectFactoryInterface::class => static fn (ContainerInterface $c) => $c->get(FrameworkBridgeInterface::class),
                RedisResolverInterface::class => static fn (ContainerInterface $c) => $c->get(FrameworkBridgeInterface::class),
                LoggerResolverInterface::class => static fn (ContainerInterface $c) => $c->get(FrameworkBridgeInterface::class),
                ResponseFactoryInterface::class => static fn (ContainerInterface $c) => $c->get(FrameworkBridgeInterface::class),
                FilesystemResolverInterface::class => static fn (ContainerInterface $c) => $c->get(FrameworkBridgeInterface::class),
                DeferInterface::class => static fn (ContainerInterface $c) => $c->get(FrameworkBridgeInterface::class),
                RedisAdapterInterface::class => HyperfRedisAdapter::class,
                DriverFactory::class => DriverFactory::class,
                DriverInterface::class => static fn (ContainerInterface $c) => (new ExcelInvoker())($c),
                ProgressStorageInterface::class => BridgeProgressStorage::class,
                ProgressInterface::class => static function (ContainerInterface $c) {
                    $bridge = $c->get(ConfigResolverInterface::class);
                    $config = $bridge->get('excel.progress', [
                        'enable' => true,
                        'prefix' => 'HyperfExcel',
                        'expire' => 3600,
                    ]);
                    return new Progress($c->get(ProgressStorageInterface::class), $config);
                },
                ExcelLogInterface::class => ExcelLogManager::class,
                ExcelInterface::class => AbstractExcel::class,
                ExcelLoggerInterface::class => ExcelLogger::class,
                ExcelQueueInterface::class => ExcelQueue::class,
                ExportPathStrategyInterface::class => DateTimeExportPathStrategy::class,
                TokenStrategyInterface::class => UuidStrategy::class,
                ProgressDisplay::class => ProgressDisplay::class,
                ExportCommandHandler::class => ExportCommandHandler::class,
                ImportCommandHandler::class => ImportCommandHandler::class,
                ProgressCommandHandler::class => ProgressCommandHandler::class,
                MessageCommandHandler::class => MessageCommandHandler::class,
            ],
            'commands' => [
                ExportCommand::class,
                ImportCommand::class,
                ProgressCommand::class,
                MessageCommand::class,
            ],
            'listeners' => [
                HyperfProgressListener::class,
                HyperfExcelLogDbListener::class,
            ],
            'processes' => [
                CleanFileProcess::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for excel.',
                    'source' => __DIR__ . '/../publish/excel.php',
                    'destination' => BASE_PATH . '/config/autoload/excel.php',
                ],
            ],
        ];
    }
}
