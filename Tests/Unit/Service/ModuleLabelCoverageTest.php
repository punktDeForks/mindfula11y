<?php
declare(strict_types=1);

/*
 * Mindful A11y extension for TYPO3 integrating accessibility tools into the backend.
 * Copyright (C) 2026  Mindful Markup, Felix Spittel
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

namespace MindfulMarkup\MindfulA11y\Tests\Unit\Service;

use MindfulMarkup\MindfulA11y\Enum\AriaLandmark;
use MindfulMarkup\MindfulA11y\Enum\HeadingType;
use MindfulMarkup\MindfulA11y\Service\ModuleLabelService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tripwire over the curated inline-label list.
 *
 * ModuleLabelService ships only the labels the components actually read, which
 * keeps the payload small but makes the list drift silently: a key the frontend
 * resolves but PHP never registers renders as an empty string in the backend,
 * and no other test notices. This suite pins both directions of that contract
 * against the sources — it needs no TYPO3 environment because it reads files.
 *
 * Scope, stated honestly: only *literal* keys are checked. Keys the components
 * compose at runtime — `mindfula11y.severity.${impact}`, the `<key>.description`
 * siblings `errorView()` builds, `scan.tab.*`, `scan.aiAudit.skill.*` — are
 * invisible here and stay hand-curated in LABEL_IDS. Two families are exempt
 * because ModuleLabelService derives them from an enum instead:
 * `structure.landmarks.role.*` and `structure.headings.level.*`.
 */
final class ModuleLabelCoverageTest extends TestCase
{
    private const SOURCE_DIRECTORY = __DIR__ . '/../../../Resources/Private/Source';
    private const LANGUAGE_FILE = __DIR__ . '/../../../Resources/Private/Language/Modules/Accessibility.xlf';

    /**
     * `mindfula11y.`-prefixed literals that are deliberately not labels, so an
     * unrecognized literal can fail instead of being skipped: a mistyped or
     * renamed label key looks exactly like "names no trans-unit".
     */
    private const NON_LABEL_LITERALS = [
        // The iframe protocol identifier (lib/structure/protocol.ts).
        'structure.v1',
    ];

    #[Test]
    public function everyLiteralLabelKeyUsedByTheComponentsIsRegistered(): void
    {
        $shipped = $this->shippedLabelIds();
        $translatable = $this->translationUnitIds();

        $missing = [];
        $unknown = [];
        foreach ($this->literalLabelIdsInSources() as $labelId => $origins) {
            if (in_array($labelId, $shipped, true) || in_array($labelId, self::NON_LABEL_LITERALS, true)) {
                continue;
            }

            $origin = $labelId . ' (' . implode(', ', $origins) . ')';
            // Naming a trans-unit means the label exists and simply is not
            // shipped; naming none means the key itself is wrong. Both render
            // as an empty string in the backend, but the fixes differ.
            if (in_array($labelId, $translatable, true)) {
                $missing[] = $origin;
            } else {
                $unknown[] = $origin;
            }
        }

        self::assertSame(
            [],
            $missing,
            'Resolved by the frontend and present in the module language file, but not shipped by '
            . 'ModuleLabelService — these render empty. Add them to LABEL_IDS.',
        );
        self::assertSame(
            [],
            $unknown,
            'These `mindfula11y.` literals name no trans-unit: either a typo, or a non-label that '
            . 'belongs in NON_LABEL_LITERALS.',
        );
    }

    #[Test]
    public function everyRegisteredLabelIdExistsInTheLanguageFile(): void
    {
        $translatable = $this->translationUnitIds();

        $unknown = array_values(array_diff($this->moduleFileLabelIds(), $translatable));

        self::assertSame(
            [],
            $unknown,
            'These label ids are registered but have no trans-unit in the module language file '
            . '(typo, or the unit was renamed/removed).',
        );
    }

    /**
     * Everything ModuleLabelService ships, including the heading-level family
     * it resolves from the TCA label file rather than the module one.
     *
     * @return list<string>
     */
    private function shippedLabelIds(): array
    {
        return array_merge(
            $this->moduleFileLabelIds(),
            array_map(
                static fn(HeadingType $type): string => 'structure.headings.level.' . $type->value,
                HeadingType::cases(),
            ),
        );
    }

    /**
     * The ids resolved from the module language file: the curated list plus the
     * landmark-role family derived from the enum.
     *
     * @return list<string>
     */
    private function moduleFileLabelIds(): array
    {
        $reflection = new \ReflectionClass(ModuleLabelService::class);
        /** @var list<string> $curated */
        $curated = $reflection->getConstant('LABEL_IDS');

        $derived = array_map(
            static fn(AriaLandmark $landmark): string
                => 'structure.landmarks.role.' . $landmark->labelSuffix(),
            AriaLandmark::cases(),
        );

        return array_merge($curated, $derived);
    }

    /**
     * Every `mindfula11y.<id>` string literal in the TypeScript sources, mapped
     * to the files it appears in.
     *
     * @return array<string, list<string>>
     */
    private function literalLabelIdsInSources(): array
    {
        $found = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::SOURCE_DIRECTORY, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'ts') {
                continue;
            }
            $contents = (string)file_get_contents($file->getPathname());
            preg_match_all('/[\'"`]mindfula11y\.([A-Za-z0-9_.]+)[\'"`]/', $contents, $matches);
            foreach ($matches[1] as $labelId) {
                $found[$labelId][$file->getFilename()] = true;
            }
        }

        return array_map(static fn(array $origins): array => array_keys($origins), $found);
    }

    /**
     * @return list<string>
     */
    private function translationUnitIds(): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::LANGUAGE_FILE), 'Module language file is readable.');

        $ids = [];
        foreach ($document->getElementsByTagName('trans-unit') as $unit) {
            $ids[] = $unit->getAttribute('id');
        }

        return $ids;
    }
}
