<?php

namespace Tests\Support;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;

class FakeFlowPlatformService
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    public function __construct(
        public Platform $platform,
        public ?Event $event = null,
        public ?Record $record = null
    ) {
    }

    public static function reset(): void
    {
        self::$calls = [];
    }

    public function firstStep(array $payload): array
    {
        self::$calls[] = 'firstStep';

        return [
            'success' => true,
            'message' => 'First step executed',
            'data' => [
                'first_step' => true,
                'input' => $payload,
            ],
        ];
    }

    public function secondStep(array $payload): array
    {
        self::$calls[] = 'secondStep';

        return [
            'success' => true,
            'message' => 'Second step executed',
            'data' => [
                'second_step' => true,
                'input' => $payload,
            ],
        ];
    }
}
