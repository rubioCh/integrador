<?php

namespace App\Services\Generic;

use App\Models\Event;
use App\Models\Platform;

interface GenericPlatformPort
{
    public function resolveEndpoint(Event $event): string;

    public function resolveMethod(Event $event): string;

    public function resolveHeaders(Event $event, Platform $platform): array;

    public function resolveQueryParams(Event $event, array $payload): array;

    public function resolveBody(Event $event, array $payload): array;

    public function resolveTimeout(Event $event): int;

    public function resolveRetryPolicy(Event $event): array;

    public function resolveIdempotencyPolicy(Event $event): array;
}
