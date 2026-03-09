<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel;

use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\BaseExcel\Data\Export\ExportData;
use BusinessG\BaseExcel\Data\Import\ImportConfig;
use BusinessG\BaseExcel\Data\Import\ImportData;
use BusinessG\BaseExcel\ExcelFunctions;
use BusinessG\BaseExcel\Progress\ProgressRecord;
use Hyperf\Context\ApplicationContext;
use RuntimeException;

ExcelFunctions::setContainerResolver(fn () => ApplicationContext::getContainer());

function excel_export(ExportConfig $config): ExportData
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }
    return ExcelFunctions::export($config);
}

function excel_import(ImportConfig $config): ImportData
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }
    return ExcelFunctions::import($config);
}

function excel_progress_pop_message(string $token, int $num = 50, bool &$isEnd = true): array
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }
    return ExcelFunctions::progressPopMessage($token, $num, $isEnd);
}

function excel_progress_push_message(string $token, string $message): void
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }
    ExcelFunctions::progressPushMessage($token, $message);
}

function excel_progress(string $token): ?ProgressRecord
{
    if (!ApplicationContext::hasContainer()) {
        throw new RuntimeException('The application context lacks the container.');
    }
    return ExcelFunctions::progress($token);
}
