<?php

namespace App\Services\Aspel;

use App\Services\Generic\GenericHttpAdapter;

class AspelApiService
{
    public function __construct(
        protected GenericHttpAdapter $httpAdapter
    ) {
    }

    public function send(
        string $platform,
        string $endpoint,
        string $method,
        array $headers,
        array $query,
        array $body,
        int $timeout,
        array $retryPolicy,
        ?GenericHttpAdapter $httpAdapter = null
    ): array {
        $adapter = $httpAdapter ?? $this->httpAdapter;

        return $adapter->send(
            $platform,
            $endpoint,
            $method,
            $headers,
            $query,
            $body,
            $timeout,
            $retryPolicy
        );
    }
}
