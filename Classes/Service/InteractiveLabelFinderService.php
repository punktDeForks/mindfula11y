<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Service;

use MindfulMarkup\MindfulA11y\Domain\Repository\InteractiveLabelRepository;
use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;

final readonly class InteractiveLabelFinderService
{
    public function __construct(
        private InteractiveLabelRepository $repository,
        private InteractiveLabelRuleProvider $ruleProvider,
        private InteractiveLabelChecker $checker,
    ) {
    }


    /**
     * @param string[] $fields
     *
     * @return array<int, array<string, mixed>>
     */
    public function find(
        int $pageId,
        string $locale,
        string $table,
        array $fields,
        InteractiveLabelType $type,
    ): array {
        if ($table === '' || $fields === []) {
            return [];
        }

        $rules = $this->ruleProvider->getRulesForType(
            $locale,
            $type,
        );

        if ($rules === []) {
            return [];
        }

        $records = $this->repository->findByPage(
            $table,
            $fields,
            $pageId,
        );

        $findings = [];

        foreach ($records as $record) {
            foreach ($fields as $field) {
                $value = trim(
                    (string)($record[$field] ?? ''),
                );

                if ($value === '') {
                    continue;
                }

                $issue = $this->checker->check(
                    $value,
                    $type,
                    $rules,
                );

                if ($issue === null) {
                    continue;
                }

                $findings[] = [
                    'table' => $table,
                    'uid' => (int)($record['uid'] ?? 0),
                    'field' => $field,
                    ...$issue,
                ];
            }
        }

        return $findings;
    }
    public function diagnose(
        int $pageId,
        string $table,
        array $fields,
    ): array {
        return $this->repository->diagnose(
            $table,
            $fields,
            $pageId,
        );
    }
}
