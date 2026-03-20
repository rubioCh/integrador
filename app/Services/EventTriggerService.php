<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTrigger;
use App\Models\EventTriggerGroup;
use App\Models\EventTriggerGroupCondition;
use Illuminate\Support\Facades\DB;

class EventTriggerService
{
    /**
     * @var list<string>
     */
    public const SUPPORTED_OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'not_contains',
        'greater_than',
        'less_than',
        'greater_than_or_equal',
        'less_than_or_equal',
        'is_null',
        'is_not_null',
        'starts_with',
        'ends_with',
    ];

    public function getEventTriggers(Event $event): array
    {
        $groups = EventTriggerGroup::query()
            ->where('event_id', $event->id)
            ->with(['conditions', 'triggers'])
            ->orderBy('id')
            ->get();

        return [
            'event_id' => $event->id,
            'groups' => $groups->map(function (EventTriggerGroup $group): array {
                $conditions = $group->conditions->map(function (EventTriggerGroupCondition $condition): array {
                    return [
                        'id' => $condition->id,
                        'field' => $condition->field,
                        'operator' => $condition->operator,
                        'value' => $condition->value,
                    ];
                })->values();

                if ($conditions->isEmpty()) {
                    $conditions = $group->triggers->map(function (EventTrigger $trigger): array {
                        return [
                            'id' => $trigger->id,
                            'field' => $trigger->field,
                            'operator' => $trigger->operator,
                            'value' => $trigger->value,
                        ];
                    })->values();
                }

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'operator' => strtolower($group->operator),
                    'active' => (bool) $group->active,
                    'conditions' => $conditions,
                ];
            })->values()->all(),
        ];
    }

    public function syncEventTriggers(Event $event, array $groups): array
    {
        DB::transaction(function () use ($event, $groups): void {
            $groupIds = collect($groups)
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            $groupsQuery = EventTriggerGroup::query()->where('event_id', $event->id);
            if (! empty($groupIds)) {
                $groupsQuery->whereNotIn('id', $groupIds);
            }
            $groupsQuery->delete();

            foreach ($groups as $index => $groupPayload) {
                $group = EventTriggerGroup::query()->updateOrCreate(
                    [
                        'id' => $groupPayload['id'] ?? null,
                        'event_id' => $event->id,
                    ],
                    [
                        'name' => $groupPayload['name'] ?? ('Group ' . ($index + 1)),
                        'operator' => strtolower($groupPayload['operator'] ?? 'and'),
                        'active' => (bool) ($groupPayload['active'] ?? true),
                    ]
                );

                $conditions = $groupPayload['conditions'] ?? $groupPayload['triggers'] ?? [];
                $conditionIds = collect($conditions)
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $conditionsQuery = EventTriggerGroupCondition::query()
                    ->where('event_trigger_group_id', $group->id);

                if (! empty($conditionIds)) {
                    $conditionsQuery->whereNotIn('id', $conditionIds);
                }

                $conditionsQuery->delete();

                foreach ($conditions as $condition) {
                    EventTriggerGroupCondition::query()->updateOrCreate(
                        [
                            'id' => $condition['id'] ?? null,
                            'event_trigger_group_id' => $group->id,
                        ],
                        [
                            'field' => $condition['field'],
                            'operator' => strtolower($condition['operator']),
                            'value' => $condition['value'] ?? null,
                        ]
                    );
                }

                // Maintain legacy compatibility by removing stale trigger rows per group.
                EventTrigger::query()
                    ->where('event_id', $event->id)
                    ->where('event_trigger_group_id', $group->id)
                    ->delete();
            }
        });

        return $this->getEventTriggers($event->fresh());
    }

    public function evaluateEventTriggers(Event $event, array $data): bool
    {
        $groupModels = EventTriggerGroup::query()
            ->where('event_id', $event->id)
            ->with(['triggers', 'conditions'])
            ->get();

        if ($groupModels->isEmpty()) {
            $legacyTriggers = EventTrigger::query()
                ->where('event_id', $event->id)
                ->whereNull('event_trigger_group_id')
                ->where('active', true)
                ->get();

            if ($legacyTriggers->isEmpty()) {
                return true;
            }

            return $this->evaluateTriggersCollection($legacyTriggers, 'and', $data);
        }

        $groupResults = [];
        foreach ($groupModels as $group) {
            if (! $group->active) {
                continue;
            }
            $groupResults[] = $this->evaluateGroup($group, $data);
        }

        if (empty($groupResults)) {
            return true;
        }

        return ! in_array(false, $groupResults, true);
    }

    private function evaluateGroup(EventTriggerGroup $group, array $data): bool
    {
        $conditions = $group->conditions ?? collect();
        $triggers = $group->triggers ?? collect();

        if ($triggers->isNotEmpty()) {
            return $this->evaluateTriggersCollection($triggers, $group->operator, $data);
        }

        if ($conditions->isNotEmpty()) {
            return $this->evaluateConditionsCollection($conditions, $group->operator, $data);
        }

        return true;
    }

    private function evaluateTriggersCollection($triggers, string $groupOperator, array $data): bool
    {
        $results = [];
        foreach ($triggers as $trigger) {
            if (! $trigger->active) {
                continue;
            }
            $results[] = $this->evaluateCondition($trigger->field, $trigger->operator, $trigger->value, $data);
        }

        return $this->combineResults($results, $groupOperator);
    }

    private function evaluateConditionsCollection($conditions, string $groupOperator, array $data): bool
    {
        $results = [];
        foreach ($conditions as $condition) {
            $results[] = $this->evaluateCondition($condition->field, $condition->operator, $condition->value, $data);
        }

        return $this->combineResults($results, $groupOperator);
    }

    private function combineResults(array $results, string $operator): bool
    {
        if (empty($results)) {
            return true;
        }

        $operator = strtolower($operator);
        if ($operator === 'or') {
            return in_array(true, $results, true);
        }

        return ! in_array(false, $results, true);
    }

    private function evaluateCondition(string $field, string $operator, $expected, array $data): bool
    {
        $actual = $this->getFieldValue($field, $data);
        $operator = strtolower($operator);
        $expected = $this->normalizeConditionValue($expected);

        switch ($operator) {
            case 'equals':
                return $actual == $expected;
            case 'not_equals':
                return $actual != $expected;
            case 'contains':
                if (is_array($actual)) {
                    return in_array($expected, $actual, true);
                }

                return is_string($actual) && is_string($expected)
                    ? str_contains(strtolower($actual), strtolower($expected))
                    : false;
            case 'not_contains':
                if (is_array($actual)) {
                    return ! in_array($expected, $actual, true);
                }

                return is_string($actual) && is_string($expected)
                    ? ! str_contains(strtolower($actual), strtolower($expected))
                    : true;
            case 'greater_than':
                return is_numeric($actual) && is_numeric($expected) && $actual > $expected;
            case 'less_than':
                return is_numeric($actual) && is_numeric($expected) && $actual < $expected;
            case 'greater_than_or_equal':
                return is_numeric($actual) && is_numeric($expected) && $actual >= $expected;
            case 'less_than_or_equal':
                return is_numeric($actual) && is_numeric($expected) && $actual <= $expected;
            case 'is_null':
                return $actual === null;
            case 'is_not_null':
                return $actual !== null;
            case 'starts_with':
                return is_string($actual) && is_string($expected)
                    ? str_starts_with(strtolower($actual), strtolower($expected))
                    : false;
            case 'ends_with':
                return is_string($actual) && is_string($expected)
                    ? str_ends_with(strtolower($actual), strtolower($expected))
                    : false;
            default:
                return false;
        }
    }

    private function getFieldValue(string $field, array $data)
    {
        $segments = explode('.', $field);
        $value = $data;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function normalizeConditionValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_key_exists('value', $value)) {
            return $value['value'];
        }

        if (array_key_exists('expected', $value)) {
            return $value['expected'];
        }

        if (count($value) === 1 && array_key_exists(0, $value)) {
            return $value[0];
        }

        return $value;
    }
}
