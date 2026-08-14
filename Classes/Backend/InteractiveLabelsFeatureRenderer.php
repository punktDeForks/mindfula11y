<?php

declare(strict_types=1);

namespace MindfulMarkup\MindfulA11y\Backend;

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
    ) {
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
                $typeFindings = $this->finderService->find(
                    $context->pageId,
                    $locale,
                    $table,
                    $fields,
                    $type,
                );

                $findings = [
                    ...$findings,
                    ...$typeFindings,
                ];
            }
        }

        $context->moduleTemplate->assign(
            'findings',
            $findings,
        );

        $diagnostics = [
            'pageTsConfig' => $pageTsConfig !== [],
            'mod' => isset($pageTsConfig['mod']),
            'mindfulA11y' => isset($pageTsConfig['mod']['mindfula11y_accessibility']),
            'interactiveLabels' => isset(
                $pageTsConfig['mod']['mindfula11y_accessibility']['interactiveLabels']
            ),
            'fields' => isset(
                $pageTsConfig['mod']['mindfula11y_accessibility']['interactiveLabels']['fields']
            ),
            'parsedFieldTypes' => count($fieldsConfig),
            'localeResolved' => $locale !== '',
            'germanButtonRuleCount' => count($testRules),
            'weiterRecognized' => $testIssue !== null,
            'testFieldsLoaded' => (
                    $pageTsConfig['mod']
                    ['mindfula11y_accessibility']
                    ['interactiveLabels']
                    ['testFieldsLoaded']
                    ?? false
                ) == 1,];

        $context->moduleTemplate->assignMultiple([
            'diagnostics' => $diagnostics,
            'pageTsConfigDebug' => $pageTsConfig,
            'fieldsConfig' => $fieldsConfig,
            'interactiveLabelLocale' => $locale,
            'findings' => $findings,
        ]);

        return $context->moduleTemplate->renderResponse(
            'Backend/InteractiveLabels',
        );
    }

    private function resolveLocale(
        ModuleContext $context,
    ): string {
        $siteLanguage =
            $context->request->getAttribute('language');

        if ($siteLanguage instanceof SiteLanguage) {
            return $siteLanguage->getLocale()->getName();
        }

        return 'en';
    }
}
