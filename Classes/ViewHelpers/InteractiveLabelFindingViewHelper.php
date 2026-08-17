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
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MindfulMarkup\MindfulA11y\ViewHelpers;

use MindfulMarkup\MindfulA11y\Enum\InteractiveLabelType;
use MindfulMarkup\MindfulA11y\Service\ModuleLabelService;
use MindfulMarkup\MindfulA11y\Service\ModuleSettingsService;
use MindfulMarkup\MindfulA11y\Service\OpenAIService;
use MindfulMarkup\MindfulA11y\Service\PermissionService;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Class InteractiveLabelFindingViewHelper.
 *
 * Renders an interactive-label-finding web component and takes care of
 * permissions required for the edit link. This is only to be used in the
 * backend. Mirrors AltlessFileReferenceViewHelper's edit-link gating.
 */
class InteractiveLabelFindingViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * Permission service instance.
     */
    protected readonly PermissionService $permissionService;
    protected readonly ModuleSettingsService $moduleSettingsService;


    /**
     * OpenAI service instance.
     */
    protected readonly OpenAIService $openAIService;

    /**
     * Backend Uri Builder instance.
     */
    protected readonly UriBuilder $backendUriBuilder;

    /**
     * Tag name.
     */
    protected $tagName = 'mindfula11y-interactive-label-finding';

    /**
     * Inject permission service.
     */
    public function injectPermissionService(PermissionService $permissionService): void
    {
        $this->permissionService = $permissionService;
    }

    public function injectModuleSettingsService(ModuleSettingsService $moduleSettingsService): void
    {
        $this->moduleSettingsService = $moduleSettingsService;
    }

    /**
     * Inject OpenAI service.
     */
    public function injectOpenAIService(OpenAIService $openAIService): void
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Inject UriBuilder.
     */
    public function injectBackendUriBuilder(UriBuilder $backendUriBuilder): void
    {
        $this->backendUriBuilder = $backendUriBuilder;
    }

    /**
     * Initialize the ViewHelper arguments.
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('finding', 'array', 'Interactive label finding to display.', true);
    }

    /**
     * Render the interactive-label-finding web component.
     */
    public function render(): string
    {
        $finding = $this->arguments['finding'];

        // Custom elements must not be self-closed — HTML has no self-closing
        // syntax for them, so `<tag />` would leave the element open.
        $this->tag->forceClosingTag(true);

        $tableName = (string)($finding['table'] ?? '');
        $fieldName = (string)($finding['field'] ?? '');
        $uid = (int)($finding['uid'] ?? 0);
        $type = $finding['type'] ?? null;
        $value = (string)($finding['value'] ?? '');
        $rule = (string)($finding['rule'] ?? '');
        $wcagCriterion = (string)($finding['wcagCriterion'] ?? '');
        $wcagLevel = (string)($finding['wcagLevel'] ?? '');
        $needsContextReview = (bool)($finding['needsContextReview'] ?? false);

        $record = ($tableName !== '' && $uid > 0)
            ? BackendUtility::getRecordWSOL($tableName, $uid)
            : null;

        if (
            $tableName !== ''
            && $fieldName !== ''
            && null !== $record
            && $this->permissionService->checkTableWriteAccess($tableName)
            && $this->permissionService->checkNonExcludeFields($tableName, [$fieldName])
            && $this->permissionService->checkRecordEditAccess($tableName, $record, [$fieldName])
        ) {
            $this->tag->addAttribute('record-edit-link', $this->backendUriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [
                    $tableName => [
                        $uid => 'edit',
                    ],
                ],
                'columnsOnly' => $fieldName,
            ]));
            $this->tag->addAttribute(
                'record-edit-link-label',
                sprintf(
                    $this->getLanguageService()->sL(ModuleLabelService::LANGUAGE_FILE . 'interactiveLabels.editRecord.label'),
                    $tableName,
                    $uid,
                ),
            );
        }

        $this->tag->addAttribute('uid', $uid);
        $this->tag->addAttribute('table', $tableName);
        $this->tag->addAttribute('field', $fieldName);

        if ($type instanceof InteractiveLabelType) {
            $this->tag->addAttribute('type', $type->value);
        }

        $this->tag->addAttribute('value', $value);
        $this->tag->addAttribute('rule', $rule);
        $this->tag->addAttribute('wcag-criterion', $wcagCriterion);
        $this->tag->addAttribute('wcag-level', $wcagLevel);

        if ($needsContextReview) {
            $this->tag->addAttribute('needs-context-review', true);
        }

        return $this->tag->render();
    }

    /**
     * Get language service.
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
