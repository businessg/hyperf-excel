<?php

namespace Vartruexuan\HyperfExcel;

use BusinessG\BaseExcel\Data\BaseConfig;
use BusinessG\BaseExcel\Data\Export\ExportConfig;
use BusinessG\BaseExcel\Data\Export\ExportData;
use BusinessG\BaseExcel\Data\Import\ImportConfig;
use BusinessG\BaseExcel\Data\Import\ImportData;
use BusinessG\BaseExcel\Driver\DriverInterface;
use BusinessG\BaseExcel\Event\AfterExport;
use BusinessG\BaseExcel\Event\AfterImport;
use BusinessG\BaseExcel\Event\BeforeExport;
use BusinessG\BaseExcel\Event\BeforeImport;
use BusinessG\BaseExcel\Event\Error;
use BusinessG\BaseExcel\Exception\ExcelException;
use BusinessG\BaseExcel\Progress\ProgressData;
use BusinessG\BaseExcel\Progress\ProgressInterface;
use BusinessG\BaseExcel\Progress\ProgressRecord;
use BusinessG\BaseExcel\Queue\ExcelQueueInterface;
use BusinessG\BaseExcel\Strategy\Token\TokenStrategyInterface;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Vartruexuan\HyperfExcel\Driver\DriverFactory;

class Excel implements ExcelInterface
{
    public EventDispatcherInterface $event;
    protected array $config;

    public function __construct(protected ContainerInterface $container, protected ProgressInterface $progress)
    {
        $config = $container->get(ConfigInterface::class);
        $this->config = $config->get('excel', []);
        $this->event = $container->get(EventDispatcherInterface::class);
    }

    public function export(ExportConfig $config): ExportData
    {
        if (empty($config->getToken())) {
            $config->setToken($this->buildToken());
        }
        $driver = $this->getDriver($config->getDriverName());
        $exportData = new ExportData(['token' => $config->getToken()]);

        try {
            $this->event->dispatch(new BeforeExport($config, $driver));

            if ($config->getIsAsync()) {
                if ($config->getOutPutType() == ExportConfig::OUT_PUT_TYPE_OUT) {
                    throw new ExcelException('Async does not support output type ExportConfig::OUT_PUT_TYPE_OUT');
                }
                $this->pushQueue($config);
                return $exportData;
            }

            $driverResult = $driver->export($config);
            $exportData = new ExportData([
                'token' => $driverResult->token,
                'response' => $driverResult->response,
            ]);

            $this->event->dispatch(new AfterExport($config, $driver, $exportData));

            return $exportData;
        } catch (ExcelException $exception) {
            $this->event->dispatch(new Error($config, $driver, $exception));
            throw $exception;
        } catch (\Throwable $throwable) {
            $this->event->dispatch(new Error($config, $driver, $throwable));
            throw $throwable;
        }
    }

    public function import(ImportConfig $config): ImportData
    {
        if (empty($config->getToken())) {
            $config->setToken($this->buildToken());
        }
        $importData = new ImportData(['token' => $config->getToken()]);
        $driver = $this->getDriver($config->getDriverName());

        try {
            $this->event->dispatch(new BeforeImport($config, $driver));
            if ($config->getIsAsync()) {
                if ($config->isReturnSheetData) {
                    throw new ExcelException('Asynchronous does not support returning sheet data');
                }
                $this->pushQueue($config);
                return $importData;
            }

            $driverResult = $driver->import($config);
            $importData = new ImportData([
                'token' => $driverResult->token,
                'sheetData' => $driverResult->sheetData,
            ]);

            $this->event->dispatch(new AfterImport($config, $driver, $importData));

            return $importData;
        } catch (ExcelException $exception) {
            $this->event->dispatch(new Error($config, $driver, $exception));
            throw $exception;
        } catch (\Throwable $throwable) {
            $this->event->dispatch(new Error($config, $driver, $throwable));
            throw $throwable;
        }
    }

    public function getProgressRecord(string $token): ?ProgressRecord
    {
        return $this->progress->getRecordByToken($token);
    }

    public function popMessage(string $token, int $num = 50): array
    {
        return $this->progress->popMessage($token, $num);
    }

    public function pushMessage(string $token, string $message): void
    {
        $this->progress->pushMessage($token, $message);
    }

    public function popMessageAndIsEnd(string $token, int $num = 50, bool &$isEnd = true): array
    {
        $progressRecord = $this->getProgressRecord($token);
        $messages = $this->popMessage($token, $num);
        $isEnd = $this->isEnd($progressRecord) && empty($messages);
        return $messages;
    }

    public function isEnd(?ProgressRecord $progressRecord): bool
    {
        return empty($progressRecord) || in_array($progressRecord->progress->status, [
            ProgressData::PROGRESS_STATUS_COMPLETE,
            ProgressData::PROGRESS_STATUS_FAIL,
        ]);
    }

    public function getDefaultDriver(): DriverInterface
    {
        return $this->container->get(DriverInterface::class);
    }

    public function getDriverByName(string $driverName): DriverInterface
    {
        return $this->container->get(DriverFactory::class)->get($driverName);
    }

    public function getDriver(?string $driverName = null): DriverInterface
    {
        $driver = $this->getDefaultDriver();
        if (!empty($driverName)) {
            $driver = $this->getDriverByName($driverName);
        }
        return $driver;
    }

    protected function pushQueue(BaseConfig $config): void
    {
        $this->container->get(ExcelQueueInterface::class)->push($config);
    }

    protected function buildToken(): string
    {
        return $this->container->get(TokenStrategyInterface::class)->getToken();
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
