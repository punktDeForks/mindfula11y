<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Service;

use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;

/**
 * Collects candidate interactive labels (links, buttons) for a page, so they
 * can be checked individually by InteractiveLabelChecker.
 *
 * ASSUMPTION: candidates are read directly from tt_content via TCA fields
 * (header, bodytext links, button-type CTypes). If findings are actually
 * sourced from rendered frontend HTML (data-mindfula11y-record-uid markers,
 * as hinted at in HeadingRecordResolver), this class needs to be replaced
 * with an HTML-scan equivalent instead.
 */
final readonly class InteractiveLabelRecordResolver
{
    public function __construct(
        private PageRepository $pageRepository,
    ) {
    }

    /**
     * @return array<int, array{
     *     value: string,
     *     type: InteractiveLabelType,
     *     recordUid: int,
     * }>
     */
    public function findCandidates(int $pageId, int $languageId): array
    {
        $candidates = [];

        $contentRecords = $this->pageRepository->getPageOverlay(
            $this->fetchContentElements($pageId, $languageId),
            $languageId,
        );

        foreach ($contentRecords as $record) {
            // Beispiel: Header als Link/Button-Kandidat, falls gesetzt.
            if (!empty($record['header'])) {
                $candidates[] = [
                    'value' => (string)$record['header'],
                    'type' => InteractiveLabelType::LINK,
                    'recordUid' => (int)$record['uid'],
                ];
            }

            // Beispiel: Buttons innerhalb von bodytext (Link-Tags mit CSS-Klasse "btn").
            foreach ($this->extractButtonLabelsFromBodytext((string)($record['bodytext'] ?? '')) as $label) {
                $candidates[] = [
                    'value' => $label,
                    'type' => InteractiveLabelType::BUTTON,
                    'recordUid' => (int)$record['uid'],
                ];
            }
        }

        return $candidates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchContentElements(int $pageId, int $languageId): array
    {
        // Platzhalter: hier müsste die tatsächliche QueryBuilder-Abfrage rein,
        // die enable-fields, Workspace-Overlay etc. berücksichtigt (siehe
        // getRawRecord()-Pattern aus HeadingRecordResolver).
        return [];
    }

    /**
     * @return array<int, string>
     */
    private function extractButtonLabelsFromBodytext(string $bodytext): array
    {
        if ($bodytext === '') {
            return [];
        }

        $labels = [];

        if (preg_match_all(
            '/<a[^>]*class="[^"]*\bbtn\b[^"]*"[^>]*>(.*?)<\/a>/is',
            $bodytext,
            $matches,
        )) {
            foreach ($matches[1] as $match) {
                $labels[] = trim(strip_tags($match));
            }
        }

        return $labels;
    }
}