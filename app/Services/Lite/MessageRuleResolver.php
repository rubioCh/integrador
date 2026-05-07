<?php

namespace App\Services\Lite;

use App\Models\MessageRule;

class MessageRuleResolver
{
    public function resolve(int $clientId, array $contactProperties, string $triggerProperty, mixed $triggerValue): ?MessageRule
    {
        $rules = MessageRule::query()
            ->with('trebleTemplate')
            ->where('client_id', $clientId)
            ->where('active', true)
            ->where('trigger_property', $triggerProperty)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matchesValue($rule->trigger_value, $triggerValue)) {
                continue;
            }

            $conditions = is_array($rule->conditions) ? $rule->conditions : [];
            $matches = true;

            foreach ($conditions as $key => $expectedValue) {
                $actualValue = $contactProperties[$key] ?? null;
                if (! $this->matchesValue($expectedValue, $actualValue)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $rule;
            }
        }

        return null;
    }

    private function matchesValue(mixed $expectedValue, mixed $actualValue): bool
    {
        return trim((string) ($expectedValue ?? '')) === trim((string) ($actualValue ?? ''));
    }
}
