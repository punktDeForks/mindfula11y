<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Service;

final readonly class InteractiveLabelAggregator
{
    private const DEFAULT_REPEATED_THRESHOLD = 2;
    private const DEFAULT_DIFFERENT_TARGETS_THRESHOLD = 2;

    private const REPEATED_RULE = 'repeated_generic_label';
    private const DIFFERENT_TARGETS_RULE = 'generic_label_different_targets';

    public function annotate(
        array $labels,
    ): array {
        if ($labels === []) {
            return [];
        }

        $labels = $this->annotateRepeated($labels);
        $labels = $this->annotateDifferentTargets($labels);

        $findings = [];

        foreach ($labels as $label) {
            $hasSingleIssue =
                isset($label['rule'])
                && (string)$label['rule'] !== '';

            $isRepeated =
                (bool)($label['isRepeated'] ?? false);

            $hasDifferentTargets =
                (bool)($label['hasDifferentTargets'] ?? false);

            if (
                !$hasSingleIssue
                && !$isRepeated
                && !$hasDifferentTargets
            ) {
                continue;
            }

            /*
             * If the normal checker did NOT find anything but a page-wide
             * rule did, promote that page-wide rule to the primary finding.
             */
            if (!$hasSingleIssue) {
                $label = $this->applyAggregateIssue($label);
            }
            $label = $this->addTranslationKeys($label);

            $findings[] = $label;
        }

        return $findings;
    }

    /**
     * @param array $labels
     * @param int $threshold
     * @return array<int, array<string, mixed>>
     */
    private function annotateRepeated(
        array $labels,
        int $threshold = self::DEFAULT_REPEATED_THRESHOLD,
    ): array {
        $counts = [];

        foreach ($labels as $label) {
            $key = $this->groupKey($label);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        foreach ($labels as $index => $label) {
            $key = $this->groupKey($label);
            $occurrences = $counts[$key] ?? 0;

            $labels[$index]['occurrenceCount'] = $occurrences;
            $labels[$index]['isRepeated'] =
                $occurrences >= $threshold;

            if ($occurrences >= $threshold) {
                $labels[$index]['repeatedRule'] =
                    self::REPEATED_RULE;
            }
        }

        return $labels;
    }

    /**
     * @param array $labels
     * @param int $threshold
     * @return array<int, array<string, mixed>>
     */
    private function annotateDifferentTargets(
        array $labels,
        int $threshold = self::DEFAULT_DIFFERENT_TARGETS_THRESHOLD,
    ): array {
        $targetsByGroup = [];

        foreach ($labels as $label) {
            $target = $this->normalizeTarget($label);

            if ($target === '') {
                continue;
            }

            $key = $this->groupKey($label);

            $targetsByGroup[$key] ??= [];
            $targetsByGroup[$key][$target] = true;
        }

        foreach ($labels as $index => $label) {
            $target = $this->normalizeTarget($label);

            if ($target === '') {
                $labels[$index]['distinctTargetCount'] = 0;
                $labels[$index]['hasDifferentTargets'] = false;

                continue;
            }

            $key = $this->groupKey($label);

            $distinctTargetCount = count(
                $targetsByGroup[$key] ?? [],
            );

            $labels[$index]['distinctTargetCount'] =
                $distinctTargetCount;

            $labels[$index]['hasDifferentTargets'] =
                $distinctTargetCount >= $threshold;

            if ($distinctTargetCount >= $threshold) {
                $labels[$index]['differentTargetsRule'] =
                    self::DIFFERENT_TARGETS_RULE;
            }
        }

        return $labels;
    }

    /**
     * Turns a page-wide issue into a normal finding when there was no
     * single-label issue.
     *
     * @param array<string, mixed> $finding
     *
     * @return array<string, mixed>
     */
    private function applyAggregateIssue(array $finding): array
    {
        $type = $finding['type'] ?? null;

        /*
         * Different targets is more specific than repetition,
         * therefore use it as the primary rule when both apply.
         */
        if (($finding['hasDifferentTargets'] ?? false) === true) {
            $finding['rule'] = self::DIFFERENT_TARGETS_RULE;
        } else {
            $finding['rule'] = self::REPEATED_RULE;
        }

        /*
         * Links primarily relate to link purpose.
         * Buttons use the descriptive-label criterion.
         */
        if (
            $type instanceof \BackedEnum
            && $type->value === 'link'
        ) {
            $finding['wcagCriterion'] = '2.4.4';
            $finding['wcagLevel'] = 'A';
        } else {
            $finding['wcagCriterion'] = '2.4.6';
            $finding['wcagLevel'] = 'AA';
        }

        return $finding;
    }

    /**
     * @param array<string, mixed> $finding
     */
    private function groupKey(array $finding): string
    {
        $type = $finding['type'] ?? '';

        if ($type instanceof \BackedEnum) {
            $type = $type->value;
        }

        $value = $this->normalizeValue(
            (string)($finding['value'] ?? ''),
        );

        return (string)$type . '|' . $value;
    }

    private function normalizeValue(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace(
            '/\s+/u',
            ' ',
            $value,
        ) ?? $value;
    }

    /**
     * @param array<string, mixed> $finding
     */
    private function normalizeTarget(array $finding): string
    {
        return trim(
            (string)($finding['target'] ?? ''),
        );
    }

    private function addTranslationKeys(array $finding): array
    {
        $rule = (string)($finding['rule'] ?? '');

        if ($rule !== '') {
            $finding['ruleTitleKey'] =
                'LLL:EXT:mindfula11y/Resources/Private/Language/Modules/Accessibility.xlf:'
                . 'findings.rule.title.'
                . $rule;

            $finding['ruleDescriptionKey'] =
                'LLL:EXT:mindfula11y/Resources/Private/Language/Modules/Accessibility.xlf:'
                . 'findings.rule.'
                . $rule;
        }

        if (!empty($finding['repeatedRule'])) {
            $finding['repeatedRuleTitleKey'] =
                'LLL:EXT:mindfula11y/Resources/Private/Language/Modules/Accessibility.xlf:'
                . 'findings.rule.title.'
                . $finding['repeatedRule'];

            $finding['repeatedRuleDescriptionKey'] =
                'LLL:EXT:mindfula11y/Resources/Private/Language/Modules/Accessibility.xlf:'
                . 'findings.rule.'
                . $finding['repeatedRule'];
        }

        if (!empty($finding['differentTargetsRule'])) {
            $finding['differentTargetsRuleTitleKey'] =
                'LLL:EXT:mindfula11y/Resources/Private/Language/Modules/Accessibility.xlf:'
                . 'findings.rule.title.'
                . $finding['differentTargetsRule'];

            $finding['differentTargetsRuleDescriptionKey'] =
                'LLL:EXT:mindfula11y/Resources/Private/Language/Modules/Accessibility.xlf:'
                . 'findings.rule.'
                . $finding['differentTargetsRule'];
        }

        return $finding;
    }
}
