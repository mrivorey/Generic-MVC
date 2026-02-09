<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;

abstract class ApiController extends BaseController
{
    protected function success(array $data, array $meta = []): string
    {
        $response = ['data' => $data];
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        return $this->json($response);
    }

    protected function error(string $message, string $code = 'error', int $status = 400): string
    {
        return $this->json([
            'error' => true,
            'code' => $code,
            'message' => $message,
        ], $status);
    }

    protected function redirect(string $url): void
    {
        throw new \RuntimeException('API controllers cannot redirect.');
    }

    protected function apiUser(): ?array
    {
        return $_REQUEST['_api_user'] ?? null;
    }
}
