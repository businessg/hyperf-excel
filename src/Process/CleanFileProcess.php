<?php

declare(strict_types=1);

namespace Vartruexuan\HyperfExcel\Process;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coordinator\Timer;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Process\AbstractProcess;
use Psr\Container\ContainerInterface;
use BusinessG\BaseExcel\Helper\Helper;
use Vartruexuan\HyperfExcel\Driver\DriverFactory;
use Psr\Log\LoggerInterface;
use Hyperf\Logger\LoggerFactory;
use Vartruexuan\HyperfExcel\Logger\ExcelLoggerInterface;

class CleanFileProcess extends AbstractProcess
{
    public string $name = 'HyperfExcel_CleanFileProcess';

    public Timer $timer;
    public array $configs = [];

    public bool $isExit = false;

    public LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $config = $this->container->get(ConfigInterface::class);
        $this->timer = new Timer();
        $this->configs = $config->get('excel', []);
        $this->logger = $this->container->get(ExcelLoggerInterface::class)->getLogger();
    }

    public function isEnable($server): bool
    {
        return $this->configs['cleanTempFile']['enable'] ?? true;
    }

    public function handle(): void
    {
        $interval = $this->configs['cleanTempFile']['interval'] ?? 1800;
        $cleanTask = function () {
            $dirs = [];
            foreach ($this->configs['drivers'] as $key => $item) {
                try {
                    $driver = $this->container->get(DriverFactory::class)->get($key);
                    $dir = $driver->getTempDir();
                    if (!$dir || !is_dir($dir) || in_array($dir, $dirs)) {
                        continue;
                    }
                    $this->cleanTempFile($dir);
                    $dirs[] = $dir;
                } catch (\Throwable $exception) {
                    $this->logger->error('Cleaning temporary files failed:' . $exception->getMessage());
                }
            }
        };

        $cleanTask();

        $timerId = $this->timer->tick($interval, $cleanTask);

        // 等待终止信号
        Coroutine::create(function () use ($timerId) {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            $this->timer->clear($timerId);
        });

        while (!$this->isExit) {
            sleep(1);
        }
    }

    /**
     * 清理文件
     *
     * @param $directory
     * @return array
     */
    public function cleanTempFile($directory): array
    {
        $maxAgeSeconds = $this->configs['cleanTempFile']['time'] ?? 1800;
        $deletedFiles = [];
        $currentTime = time();

        $files = scandir($directory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $directory . DIRECTORY_SEPARATOR . $file;

            if (is_file($filePath)) {
                $fileTime = filemtime($filePath);
                $ageSeconds = $currentTime - $fileTime;

                if ($ageSeconds > $maxAgeSeconds) {
                    if (Helper::deleteFile($filePath)) {
                        $deletedFiles[] = $filePath;
                    }
                }
            }
        }
        return $deletedFiles;
    }
}