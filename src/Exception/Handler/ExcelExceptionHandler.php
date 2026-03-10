<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Exception\Handler;

use BusinessG\BaseExcel\Exception\ExcelException;
use BusinessG\BaseExcel\Service\ExcelBusinessService;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Hyperf ExcelException 异常处理器。
 *
 * 捕获 ExcelException 并按 http 配置的字段格式返回 JSON 错误响应。
 * 通过 ConfigProvider 自动注册到异常处理链中。
 */
class ExcelExceptionHandler extends ExceptionHandler
{
    public function __construct(protected ExcelBusinessService $service)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {
        $this->stopPropagation();

        $code = $throwable->getCode() ?: 500;
        $body = $this->service->errorResponse($code, $throwable->getMessage());

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream(json_encode($body, JSON_UNESCAPED_UNICODE)));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ExcelException;
    }
}
