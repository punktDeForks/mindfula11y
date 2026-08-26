<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Backend;

use MindfulMarkup\MindfulA11y\Service\BackendPageLanguageService;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;
use MindfulMarkup\MindfulA11y\Service\InteractiveLabelFinderService;
use MindfulMarkup\MindfulA11y\Service\ModuleSettingsService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use MindfulMarkup\MindfulA11y\Service\InteractiveLabelChecker;
use MindfulMarkup\MindfulA11y\Service\InteractiveLabelRuleProvider;
use MindfulMarkup\MindfulA11y\Service\InteractiveLabelAggregator;
use TYPO3\CMS\Core\Page\PageRenderer;

final readonly class InteractiveLabelsFeatureRenderer implements FeatureRendererInterface
{
    public function __construct(
        private InteractiveLabelFinderService $finderService,
        private ModuleSettingsService $moduleSettingsService,
        private InteractiveLabelRuleProvider $ruleProvider,
        private InteractiveLabelChecker $checker,
        private BackendPageLanguageService $backendPageLanguageService,
        private InteractiveLabelAggregator $aggregator,
        private PageRenderer $pageRenderer,
    ) {
    }
    private function resolveLocale(ModuleContext $context): string
    {
        $site = $context->request->getAttribute('site');

        if (!$site instanceof SiteInterface) {
            return 'en';
        }

        $languages = $this->backendPageLanguageService
            ->getSelectableLanguages(
                $site,
                $context->pageId,
            );

        foreach ($languages as $language) {
            if ($language->getLanguageId() !== $context->languageId) {
                continue;
            }

            return $language->getLocale()->getName();
        }

        return 'en';
    }
    public function render(
        ModuleContext $context,
    ): ResponseInterface {
        $pageTsConfig =
            $this->moduleSettingsService->getConvertedPageTsConfig(
                $context->pageId,
            );

        $fieldsConfig =
            $this->moduleSettingsService->getInteractiveLabelFields(
                $pageTsConfig,
            );

        $locale = $this->resolveLocale($context);

        $labels = [];

        foreach (InteractiveLabelType::cases() as $type) {
            $tableFields = $fieldsConfig[$type->value] ?? [];

            foreach ($tableFields as $table => $fields) {
                $currentLabels = $this->finderService->find(
                    $context->pageId,
                    $context->languageId,
                    $locale,
                    $table,
                    $fields,
                    $type,
                );

                $labels = [
                    ...$labels,
                    ...$currentLabels,
                ];
            }
        }

        $findings = $this->aggregator->annotate($labels);

        $context->moduleTemplate->assignMultiple([
            'pageTsConfigDebug' => $pageTsConfig,
            'fieldsConfig' => $fieldsConfig,
            'interactiveLabelLocale' => $locale,
            'findings' => $findings,
        ]);

        $this->pageRenderer->loadJavaScriptModule(
            '@mindfulmarkup/mindfula11y/element/notice/notice.js',
        );

        $this->pageRenderer->addCssFile(
            'EXT:mindfula11y/Resources/Public/Css/backend.css',
        );

        $this->pageRenderer->loadJavaScriptModule(
            '@mindfulmarkup/mindfula11y/element/interactive-label-finding/interactive-label-finding.js',
        );

        return $context->moduleTemplate->renderResponse(
            'Backend/InteractiveLabels',
        );
    }
}
