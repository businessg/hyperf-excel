<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Listener;

use Hyperf\AsyncQueue\Event\AfterHandle;
use Hyperf\AsyncQueue\Event\BeforeHandle;
use Hyperf\AsyncQueue\Event\FailedHandle;
use Hyperf\AsyncQueue\Event\RetryHandle;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use BusinessG\BaseExcel\Event\AfterExport;
use BusinessG\BaseExcel\Event\AfterExportData;
use BusinessG\BaseExcel\Event\AfterExportExcel;
use BusinessG\BaseExcel\Event\AfterExportOutput;
use BusinessG\BaseExcel\Event\AfterExportSheet;
use BusinessG\BaseExcel\Event\AfterImport;
use BusinessG\BaseExcel\Event\AfterImportData;
use BusinessG\BaseExcel\Event\AfterImportExcel;
use BusinessG\BaseExcel\Event\AfterImportSheet;
use BusinessG\BaseExcel\Event\BeforeExport;
use BusinessG\BaseExcel\Event\BeforeExportData;
use BusinessG\BaseExcel\Event\BeforeExportExcel;
use BusinessG\BaseExcel\Event\BeforeExportOutput;
use BusinessG\BaseExcel\Event\BeforeExportSheet;
use BusinessG\BaseExcel\Event\BeforeImport;
use BusinessG\BaseExcel\Event\BeforeImportData;
use BusinessG\BaseExcel\Event\BeforeImportExcel;
use BusinessG\BaseExcel\Event\BeforeImportSheet;
use BusinessG\BaseExcel\Event\Error;
use Vartruexuan\HyperfExcel\Logger\ExcelLoggerInterface;

/**
 * 监听输出日志
 */
abstract class BaseListener implements ListenerInterface
{
    protected ContainerInterface $container;
    protected LoggerInterface $logger;

    public function __construct(ContainerInterface $container, ExcelLoggerInterface $logger)
    {
        $this->container = $container;
        $this->logger = $logger->getLogger();
    }

    public function listen(): array
    {
        return [
            // 导出
            BeforeExport::class,
            BeforeExportExcel::class,
            BeforeExportData::class,
            BeforeExportSheet::class,
            BeforeExportOutput::class,

            AfterExport::class,
            AfterExportData::class,
            AfterExportExcel::class,
            AfterExportSheet::class,
            AfterExportOutput::class,

            // 导入
            BeforeImport::class,
            BeforeImportExcel::class,
            BeforeImportData::class,
            BeforeImportSheet::class,

            AfterImport::class,
            AfterImportData::class,
            AfterImportExcel::class,
            AfterImportSheet::class,

            // error
            Error::class,
        ];
    }

    protected function getEventClass(object $event)
    {
        return lcfirst(basename(str_replace('\\', '/', get_class($event))));
    }

    public function process(object $event): void
    {
        $className = $this->getEventClass($event);
        $this->{$className}($event);
    }

    abstract function beforeExport(object $event);

    abstract function beforeExportExcel(object $event);

    abstract function beforeExportData(object $event);

    abstract function beforeExportSheet(object $event);

    abstract function beforeExportOutput(object $event);

    abstract function afterExport(object $event);

    abstract function afterExportData(object $event);

    abstract function afterExportExcel(object $event);

    abstract function afterExportSheet(object $event);

    abstract function afterExportOutput(object $event);

    abstract function beforeImport(object $event);

    abstract function beforeImportExcel(object $event);

    abstract function beforeImportData(object $event);

    abstract function beforeImportSheet(object $event);

    abstract function afterImport(object $event);

    abstract function afterImportData(object $event);

    abstract function afterImportExcel(object $event);

    abstract function afterImportSheet(object $event);

    abstract function error(object $event);
}