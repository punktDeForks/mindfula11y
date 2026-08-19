<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Service;

use MindfulMarkup\MindfulA11y\Domain\Repository\InteractiveLabelRepository;
use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;

final readonly class InteractiveLabelFinderService
{
    private const TARGET_FIELD = 'button_link';

    public function __construct(
        private InteractiveLabelRepository $repository,
        private InteractiveLabelRuleProvider $ruleProvider,
        private InteractiveLabelChecker $checker,
    ) {
    }

    /**
     * Returns all interactive labels found on the page.
     *
     * A label does not need to have a single-value issue. This is important
     * because page-wide rules such as repeated labels and identical labels
     * with different targets need to see all labels.
     *
     * @param string[] $fields
     *
     * @return array<int, array<string, mixed>>
     */
    public function find(
        int $pageId,
        int $languageId,
        string $locale,
        string $table,
        array $fields,
        InteractiveLabelType $type,
    ): array {
        if ($table === '' || $fields === []) {
            return [];
        }

        $rules = $this->ruleProvider->getRules($locale);

        $records = $this->repository->findByPage(
            $table,
            $fields,
            $pageId,
            $languageId,
            self::TARGET_FIELD,
        );

        $labels = [];

        foreach ($records as $record) {
            $target = trim(
                (string)($record[self::TARGET_FIELD] ?? ''),
            );

            foreach ($fields as $field) {
                $value = trim(
                    (string)($record[$field] ?? ''),
                );

                if ($value === '') {
                    continue;
                }

                $issue = null;

                if ($rules !== []) {
                    $issue = $this->checker->check(
                        $value,
                        $type,
                        $rules,
                    );
                }

                $label = [
                    'table' => $table,
                    'uid' => (int)($record['uid'] ?? 0),
                    'field' => $field,
                    'value' => $value,
                    'type' => $type,
                    'target' => $target,

                    // Tells the aggregator whether the normal
                    // per-label checker already found something.
                    'hasSingleIssue' => $issue !== null,
                ];

                if ($issue !== null) {
                    $label = [
                        ...$label,
                        ...$issue,
                    ];
                }

                // IMPORTANT:
                // Do not continue when $issue === null.
                // The aggregator must also see valid-looking labels.
                $labels[] = $label;
            }
        }

        return $labels;
    }
}
