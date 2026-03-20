<?php

namespace App\Services;

use App\Models\Record;
use Illuminate\Support\Facades\Cache;

class EventCacheService
{
    public function clearCache(): void
    {
        Cache::forget('events:cache');
        Cache::forget('events:flow');
    }

    public function clearRecords(): int
    {
        return Record::query()->delete();
    }

    public function clearAll(): int
    {
        $this->clearCache();

        return $this->clearRecords();
    }
}
