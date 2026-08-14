<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Service;

use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;

final readonly class InteractiveLabelChecker
{
    /**
     * @param array<int, array<string, string>> $rules
     *
     * @return array{
     *     type: InteractiveLabelType,
     *     value: string,
     *     rule: string,
     *     wcagCriterion: string,
     *     wcagLevel: string,
     *     needsContextReview: bool
     * }|null
     */
    public function check(
        string $value,
        InteractiveLabelType $type,
        array $rules,
    ): ?array {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $normalizedValue = $this->normalize($value);

        foreach ($rules as $rule) {
            $term = trim(
                (string)($rule['term'] ?? ''),
            );

            if ($term === '') {
                continue;
            }

            if ($normalizedValue !== $this->normalize($term)) {
                continue;
            }

            return [
                'type' => $type,
                'value' => $value,
                'rule' => (string)($rule['rule'] ?? ''),
                'wcagCriterion' => (string)($rule['wcag'] ?? ''),
                'wcagLevel' => (string)($rule['level'] ?? ''),
                'needsContextReview' => true,
            ];
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace(
            '/\s+/u',
            ' ',
            $value,
        ) ?? $value;
    }
}
