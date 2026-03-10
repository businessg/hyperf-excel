<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Http\Controller;

use BusinessG\BaseExcel\Service\ExcelBusinessService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class ExcelController
{
    public function __construct(
        protected ContainerInterface $container,
        protected ExcelBusinessService $service,
        protected RequestInterface $request,
        protected ResponseInterface $response
    ) {
    }

    public function export(): PsrResponseInterface
    {
        $businessId = $this->request->input('business_id', '');
        $param = $this->request->input('param', []);

        $result = $this->service->exportByBusinessId($businessId, $param);

        if ($result['response'] instanceof PsrResponseInterface) {
            return $result['response'];
        }

        return $this->response->json($this->service->successResponse($result));
    }

    public function import(): PsrResponseInterface
    {
        $businessId = $this->request->input('business_id', '');
        $url = $this->request->input('url', '');

        $result = $this->service->importByBusinessId($businessId, $url);

        return $this->response->json($this->service->successResponse(['token' => $result['token']]));
    }

    public function progress(): PsrResponseInterface
    {
        $token = $this->request->input('token', '');

        return $this->response->json(
            $this->service->successResponse(
                $this->service->getProgressArrayByToken($token)
            )
        );
    }

    public function message(): PsrResponseInterface
    {
        $token = $this->request->input('token', '');

        return $this->response->json(
            $this->service->successResponse(
                $this->service->getMessageByToken($token)
            )
        );
    }

    public function info(): PsrResponseInterface
    {
        $businessId = $this->request->input('business_id', '');

        return $this->response->json(
            $this->service->successResponse(
                $this->service->getInfoByBusinessId($businessId)
            )
        );
    }

    public function upload(): PsrResponseInterface
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->response->json($this->service->errorResponse(422, '请上传有效的文件'));
        }

        $extension = $file->getExtension();
        if (!in_array($extension, ['xlsx', 'xls'])) {
            return $this->response->json($this->service->errorResponse(422, '仅支持 xlsx, xls 格式'));
        }

        $dir = BASE_PATH . '/runtime/excel-import/' . date('Y/m/d');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $fileName = bin2hex(random_bytes(20)) . '.' . $extension;
        $fullPath = $dir . '/' . $fileName;
        $file->moveTo($fullPath);

        return $this->response->json(
            $this->service->successResponse([
                'path' => $fullPath,
                'url' => $fullPath,
            ])
        );
    }
}
