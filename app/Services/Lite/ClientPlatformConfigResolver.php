<?php

namespace App\Services\Lite;

use App\Models\PlatformConnection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ClientPlatformConfigResolver
{
    public function forClientAndPlatform(int $clientId, string $platformType): PlatformConnection
    {
        $connection = PlatformConnection::query()
            ->where('client_id', $clientId)
            ->where('platform_type', $platformType)
            ->where('active', true)
            ->orderBy('id')
            ->first();

        if ($connection) {
            return $connection;
        }

        throw (new ModelNotFoundException())->setModel(PlatformConnection::class, [
            'client_id' => $clientId,
            'platform_type' => $platformType,
        ]);
    }
}
