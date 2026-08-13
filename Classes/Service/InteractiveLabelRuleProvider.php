<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Service;

use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;

final readonly class InteractiveLabelRuleProvider
{
    /**
     * @return array<int, array<string, string>>
     */
    public function getRulesForType(
        string $locale,
        InteractiveLabelType $type,
    ): array {
        $languageCode = $this->getLanguageCode($locale);
        $file = $this->getRuleFile($languageCode);

        if ($file === null) {
            return [];
        }

        $rules = require $file;

        if (!is_array($rules)) {
            return [];
        }

        $typeRules = $rules[$type->value] ?? [];

        return is_array($typeRules)
            ? $typeRules
            : [];
    }

    private function getLanguageCode(string $locale): string
    {
        $locale = str_replace('_', '-', trim($locale));

        $parts = explode('-', $locale);

        return strtolower($parts[0] ?? '');
    }

    private function getRuleFile(string $languageCode): ?string
    {
        if ($languageCode === '') {
            return null;
        }

        $file = dirname(__DIR__, 2)
            . '/Configuration/InteractiveLabels/'
            . $languageCode
            . '.php';

        return is_file($file)
            ? $file
            : null;
    }
}