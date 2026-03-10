<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Process;

use BusinessG\BaseExcel\Config\ExcelConfig;
use BusinessG\BaseExcel\Driver\DriverFactory;
use BusinessG\BaseExcel\Helper\Helper;
use BusinessG\BaseExcel\Logger\ExcelLoggerInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class CleanFileProcess extends AbstractProcess
{
    public string $name = 'HyperfExcel_CleanFileProcess';

    public Timer $timer;

    public bool $isExit = false;

    public LoggerInterface $logger;

    protected ExcelConfig $excelConfig;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $config = $this->container->get(ConfigInterface::class);
        $this->excelConfig = ExcelConfig::fromArray($config->get('excel', []));
        $this->timer = new Timer();
        $this->logger = $this->container->get(ExcelLoggerInterface::class)->getLogger();
    }

    public function isEnable($server): bool
    {
        return $this->excelConfig->cleanup->enabled;
    }

    public function handle(): void
    {
        $interval = $this->excelConfig->cleanup->interval;
        $cleanTask = function () {
            $driverFactory = $this->container->get(DriverFactory::class);
            $dirs = Helper::getDirectoriesToClean($driverFactory);
            foreach ($dirs as $dir) {
                try {
                    $this->cleanTempFile($dir);
                } catch (\Throwable $exception) {
                    $this->logger->error('Cleaning temporary files failed:' . $exception->getMessage());
                }
            }
        };

        $cleanTask();

        $timerId = $this->timer->tick($interval, $cleanTask);

        Coroutine::create(function () use ($timerId) {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            $this->timer->clear($timerId);
        });

        while (!$this->isExit) {
            sleep(1);
        }
    }

    public function cleanTempFile(string $directory): array
    {
        return Helper::cleanTempDirectory($directory, $this->excelConfig->cleanup->maxAge);
    }
}