<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel;

use BusinessG\BaseExcel\Console\ExportCommandHandler;
use BusinessG\BaseExcel\Console\ImportCommandHandler;
use BusinessG\BaseExcel\Console\MessageCommandHandler;
use BusinessG\BaseExcel\Console\ProgressCommandHandler;
use BusinessG\BaseExcel\Console\ProgressDisplay;
use BusinessG\BaseExcel\Driver\DriverInterface;
use BusinessG\BaseExcel\Progress\ProgressInterface;
use BusinessG\BaseExcel\Progress\ProgressStorageInterface;
use BusinessG\BaseExcel\Queue\ExcelQueueInterface;
use BusinessG\BaseExcel\Strategy\Path\DateTimeExportPathStrategy;
use BusinessG\BaseExcel\Strategy\Path\ExportPathStrategyInterface;
use BusinessG\BaseExcel\Strategy\Token\TokenStrategyInterface;
use BusinessG\BaseExcel\Strategy\Token\UuidStrategy;
use Vartruexuan\HyperfExcel\Command\ExportCommand;
use Vartruexuan\HyperfExcel\Command\ImportCommand;
use Vartruexuan\HyperfExcel\Command\MessageCommand;
use Vartruexuan\HyperfExcel\Command\ProgressCommand;
use Vartruexuan\HyperfExcel\Db\ExcelLogInterface;
use Vartruexuan\HyperfExcel\Db\ExcelLogManager;
use Vartruexuan\HyperfExcel\Driver\DriverFactory;
use Vartruexuan\HyperfExcel\Listener\ProgressListener;
use Vartruexuan\HyperfExcel\Logger\ExcelLogger;
use Vartruexuan\HyperfExcel\Logger\ExcelLoggerInterface;
use Vartruexuan\HyperfExcel\Process\CleanFileProcess;
use Vartruexuan\HyperfExcel\Progress\HyperfProgressStorage;
use Vartruexuan\HyperfExcel\Progress\ProgressFactory;
use Vartruexuan\HyperfExcel\Queue\AsyncQueue\ExcelQueue;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                DriverInterface::class => ExcelInvoker::class,
                ProgressStorageInterface::class => HyperfProgressStorage::class,
                ProgressInterface::class => ProgressFactory::class,
                ExcelLogInterface::class => ExcelLogManager::class,
                ExcelInterface::class => Excel::class,
                \BusinessG\BaseExcel\ExcelInterface::class => Excel::class,
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
                ProgressListener::class,
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
