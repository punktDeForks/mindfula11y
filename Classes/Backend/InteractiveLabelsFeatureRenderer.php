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

final readonly class InteractiveLabelsFeatureRenderer implements FeatureRendererInterface
{
    public function __construct(
        private InteractiveLabelFinderService $finderService,
        private ModuleSettingsService $moduleSettingsService,
        private InteractiveLabelRuleProvider $ruleProvider,
        private InteractiveLabelChecker $checker,
        private BackendPageLanguageService $backendPageLanguageService,
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
        $testRules = $this->ruleProvider->getRulesForType(
            'de-DE',
            InteractiveLabelType::BUTTON,
        );

        $testIssue = $this->checker->check(
            'Weiter',
            InteractiveLabelType::BUTTON,
            $testRules,
        );

        $context->moduleTemplate->assign(
            'selfTestSuccessful',
            $testIssue !== null,
        );

        $pageTsConfig =
            $this->moduleSettingsService->getConvertedPageTsConfig(
                $context->pageId,
            );

        $fieldsConfig =
            $this->moduleSettingsService->getInteractiveLabelFields(
                $pageTsConfig,
            );

        $locale = $this->resolveLocale($context);

        $findings = [];

        foreach (InteractiveLabelType::cases() as $type) {
            $tableFields = $fieldsConfig[$type->value] ?? [];

            foreach ($tableFields as $table => $fields) {
                $currentFindings = $this->finderService->find(
                    $context->pageId,
                    $context->languageId,
                    $locale,
                    $table,
                    $fields,
                    $type,
                );

                $findings = [
                    ...$findings,
                    ...$currentFindings,
                ];
            }
        }

        $context->moduleTemplate->assign(
            'findings',
            $findings,
        );

        $context->moduleTemplate->assignMultiple([
            'pageTsConfigDebug' => $pageTsConfig,
            'fieldsConfig' => $fieldsConfig,
            'interactiveLabelLocale' => $locale,
            'findings' => $findings,
        ]);

        return $context->moduleTemplate->renderResponse(
            'Backend/InteractiveLabels',
        );
    }
}
