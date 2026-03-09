<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel;

use RuntimeException;
use Hyperf\Context\ApplicationContext;
use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\BaseExcel\Data\Export\ExportData;
use BusinessG\BaseExcel\Data\Import\ImportConfig;
use BusinessG\BaseExcel\Data\Import\ImportData;
use BusinessG\BaseExcel\Progress\ProgressRecord;

function excel_export(ExportConfig $config): ExportData
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }

    $container = ApplicationContext::getContainer();

    if (!$container->has(ExcelInterface::class)) {
        throw new RuntimeException('ExcelInterface is missing in container.');
    }

    return $container->get(ExcelInterface::class)->export($config);
}

function excel_import(ImportConfig $config): ImportData
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }

    $container = ApplicationContext::getContainer();

    if (!$container->has(ExcelInterface::class)) {
        throw new RuntimeException('ExcelInterface is missing in container.');
    }
    return $container->get(ExcelInterface::class)->import($config);
}

function excel_progress_pop_message(string $token, int $num = 50, bool &$isEnd = true): array
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }

    $container = ApplicationContext::getContainer();

    if (!$container->has(ExcelInterface::class)) {
        throw new RuntimeException('ExcelInterface is missing in container.');
    }
    return $container->get(ExcelInterface::class)->popMessageAndIsEnd($token, $num, $isEnd);
}

function excel_progress_push_message(string $token, string $message)
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }

    $container = ApplicationContext::getContainer();

    if (!$container->has(ExcelInterface::class)) {
        throw new RuntimeException('ExcelInterface is missing in container.');
    }
    return $container->get(ExcelInterface::class)->pushMessage($token, $message);
}

function excel_progress(string $token): ?ProgressRecord
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }

    $container = ApplicationContext::getContainer();

    if (!$container->has(ExcelInterface::class)) {
        throw new RuntimeException('ExcelInterface is missing in container.');
    }
    return $container->get(ExcelInterface::class)->getProgressRecord($token);
}
