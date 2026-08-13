<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Service;

use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;

final readonly class InteractiveLabelFinderService
{
    public function __construct(
        private InteractiveLabelRuleProvider $ruleProvider,
        private InteractiveLabelChecker $checker,
        private InteractiveLabelRecordResolver $recordResolver,
    ) {
    }

    public function checkLabel(
        string $value,
        string $locale,
        InteractiveLabelType $type,
    ): ?array {
        $rules = $this->ruleProvider->getRulesForType(
            $locale,
            $type,
        );

        return $this->checker->check(
            $value,
            $type,
            $rules,
        );
    }

    /**
     * @param array<string, mixed> $pageTsConfig
     *
     * @return array<int, array{
     *     type: InteractiveLabelType,
     *     value: string,
     *     rule: string,
     *     wcagCriterion: string,
     *     wcagLevel: string,
     *     needsContextReview: bool,
     *     recordUid: int
     * }>
     */
    public function find(
        int $pageId,
        int $languageId,
        array $pageTsConfig,
    ): array {
        $locale = (string)($pageTsConfig['locale'] ?? 'en');

        $findings = [];

        foreach ($this->recordResolver->findCandidates($pageId, $languageId) as $candidate) {
            $result = $this->checkLabel(
                $candidate['value'],
                $locale,
                $candidate['type'],
            );

            if ($result !== null) {
                $findings[] = [
                    ...$result,
                    'recordUid' => $candidate['recordUid'],
                ];
            }
        }

        return $findings;
    }
}