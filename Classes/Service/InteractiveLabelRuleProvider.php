<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Service;

use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;

final readonly class InteractiveLabelRuleProvider
{
    /**
     * Loads the full rule set for a locale. Rules are no longer split by
     * element type at file level — the new format carries per-criterion
     * `appliesTo` (['link'], ['button'], or both) inside each rule's `wcag`
     * entries instead, so the same term list works for both element types
     * and InteractiveLabelChecker resolves which criteria apply once it
     * knows the actual element type of the record being checked.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRules(string $locale): array
    {
        $languageCode = $this->getLanguageCode($locale);
        $file = $this->getRuleFile($languageCode);

        if ($file === null) {
            return [];
        }

        $rules = require $file;

        return is_array($rules) ? $rules : [];
    }

    private function getLanguageCode(string $locale): string
    {
        $locale = str_replace(
            '_',
            '-',
            trim($locale),
        );

        $parts = explode('-', $locale);

        return strtolower(
            $parts[0] ?? '',
        );
    }

    private function getRuleFile(
        string $languageCode,
    ): ?string {
        $fileName = match ($languageCode) {
            'de' => 'German.php',
            'en' => 'English.php',
            default => null,
        };

        if ($fileName === null) {
            return null;
        }

        $file = dirname(__DIR__, 2)
            . '/Configuration/InteractiveLabels/'
            . $fileName;

        return is_file($file)
            ? $file
            : null;
    }
}
