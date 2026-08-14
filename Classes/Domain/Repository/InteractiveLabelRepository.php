<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class InteractiveLabelRepository
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @param string[] $fields
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByPage(
        string $table,
        array $fields,
        int $pageId,
    ): array {
        if (!isset($GLOBALS['TCA'][$table])) {
            return [];
        }

        $validFields = array_values(
            array_filter(
                $fields,
                static fn(string $field): bool =>
                isset($GLOBALS['TCA'][$table]['columns'][$field]),
            ),
        );

        if ($validFields === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool
            ->getQueryBuilderForTable($table);

        return $queryBuilder
            ->select(
                'uid',
                'pid',
                ...$validFields,
            )
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter(
                        $pageId,
                        Connection::PARAM_INT,
                    ),
                ),
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param string[] $fields
     *
     * @return array{
     *     tableInTca: bool,
     *     validFieldCount: int,
     *     recordsOnPage: int
     * }
     */
    public function diagnose(
        string $table,
        array $fields,
        int $pageId,
    ): array {
        $tableInTca = isset($GLOBALS['TCA'][$table]);

        $validFields = [];

        if ($tableInTca) {
            $validFields = array_values(
                array_filter(
                    $fields,
                    static fn(string $field): bool =>
                    isset($GLOBALS['TCA'][$table]['columns'][$field]),
                ),
            );
        }

        $recordCount = 0;

        if ($tableInTca && $validFields !== []) {
            $queryBuilder = $this->connectionPool
                ->getQueryBuilderForTable($table);

            $recordCount = (int)$queryBuilder
                ->count('uid')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter(
                            $pageId,
                            Connection::PARAM_INT,
                        ),
                    ),
                )
                ->executeQuery()
                ->fetchOne();
        }

        return [
            'tableInTca' => $tableInTca,
            'validFieldCount' => count($validFields),
            'recordsOnPage' => $recordCount,
        ];
    }
}
