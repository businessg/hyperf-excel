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
use Vartruexuan\HyperfExcel\Driver\DriverFactory;
use Vartruexuan\HyperfExcel\Helper\Helper;
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
                    // 清理临时文件
                    $this->cleanTempFile($dir);
                    // 清理图片缓存
                    $this->cleanupImageCache($dir);
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

    /**
     * 清理图片缓存
     * 清理指定临时目录下所有过期的 token 目录（包含图片缓存）
     *
     * @param string $tempDir 临时目录路径
     * @return array 返回已删除的目录路径数组
     */
    public function cleanupImageCache(string $tempDir): array
    {
        $maxAgeSeconds = $this->configs['cleanTempFile']['time'] ?? 1800;
        $deletedDirs = [];
        $currentTime = time();

        if (!is_dir($tempDir)) {
            return $deletedDirs;
        }

        $items = scandir($tempDir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $tempDir . DIRECTORY_SEPARATOR . $item;

            // 只处理目录（token 目录）
            if (!is_dir($itemPath)) {
                continue;
            }

            // 检查目录修改时间
            $dirTime = filemtime($itemPath);
            $ageSeconds = $currentTime - $dirTime;

            // 如果目录超过指定时间，删除整个目录（包括其中的图片缓存）
            if ($ageSeconds > $maxAgeSeconds) {
                if ($this->deleteDirectory($itemPath)) {
                    $deletedDirs[] = $itemPath;
                }
            }
        }

        return $deletedDirs;
    }

    /**
     * 递归删除目录及其所有内容
     *
     * @param string $dir 要删除的目录路径
     * @return bool 删除成功返回 true，失败返回 false
     */
    protected function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                // 递归删除子目录
                $this->deleteDirectory($filePath);
            } else {
                // 删除文件
                Helper::deleteFile($filePath);
            }
        }

        // 删除空目录
        return @rmdir($dir);
    }
}