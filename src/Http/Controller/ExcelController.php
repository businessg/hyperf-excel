<?php

declare(strict_types=1);

namespace BusinessG\HyperfExcel\Http\Controller;

use BusinessG\BaseExcel\Contract\FilesystemResolverInterface;
use BusinessG\BaseExcel\Service\ExcelBusinessService;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class ExcelController
{
    public function __construct(
        protected ContainerInterface $container,
        protected ExcelBusinessService $service,
        protected FilesystemResolverInterface $filesystemResolver,
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

        $config = $this->container->get(ConfigInterface::class);
        $uploadDisk = $config->get('excel.http.upload.disk', 'local');
        $uploadDir = $config->get('excel.http.upload.dir', 'excel-import');
        $relativePath = $uploadDir . '/' . date('Y/m/d') . '/' . bin2hex(random_bytes(20)) . '.' . $extension;

        $filesystem = $this->filesystemResolver->getFilesystem($uploadDisk);
        $stream = fopen($file->getRealPath(), 'r');
        $filesystem->writeStream($relativePath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $fullPath = $this->getUploadFullPath($config, $uploadDisk, $relativePath);

        return $this->response->json(
            $this->service->successResponse([
                'path' => $fullPath,
                'url' => $fullPath,
            ])
        );
    }

    protected function getUploadFullPath(ConfigInterface $config, string $disk, string $relativePath): string
    {
        $root = $config->get('file.storage.' . $disk . '.root');
        if ($root !== null && $root !== '') {
            return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        }
        return $relativePath;
    }
}
