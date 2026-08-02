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

namespace MindfulMarkup\MindfulA11y\Tests\Functional\ViewHelpers;

use MindfulMarkup\MindfulA11y\Domain\Model\StructureAnalysisTicket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Renders the landmark ViewHelper through real Fluid to pin the element/role
 * mapping — in particular that banner and contentinfo carry an explicit role
 * attribute: header/footer only expose these landmark roles implicitly when
 * they are NOT descendants of sectioning content, and content elements
 * typically render inside main/section.
 */
final class LandmarkViewHelperTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'mindfulmarkup/mindfula11y',
    ];

    /**
     * @param array<string, mixed> $variables
     */
    private function render(string $source, ?ServerRequestInterface $request = null, array $variables = []): string
    {
        $context = $this->get(RenderingContextFactory::class)->create([], $request);
        $context->getTemplatePaths()->setTemplateSource(
            '<html xmlns:mindfula11y="http://typo3.org/ns/MindfulMarkup/MindfulA11y/ViewHelpers" data-namespace-typo3-fluid="true">'
            . $source
            . '</html>'
        );

        $view = new TemplateView($context);
        $view->assignMultiple($variables);

        return trim($view->render());
    }

    private function structureAnalysisRequest(): ServerRequestInterface
    {
        $ticket = new StructureAnalysisTicket(
            requestId: str_repeat('ab', 16),
            pageId: 1,
            languageId: 1,
            workspaceId: 0,
            pageRecordSnapshot: str_repeat('a', 64),
            backendUserId: 1,
            backendOrigin: 'https://backend.example',
            frontendOrigin: 'https://frontend.example',
            target: '/fr/',
            expiresAt: PHP_INT_MAX,
        );

        return (new ServerRequest('https://frontend.example/fr/'))
            ->withAttribute(StructureAnalysisTicket::REQUEST_ATTRIBUTE, $ticket);
    }

    #[Test]
    public function bannerRendersHeaderWithExplicitRole(): void
    {
        $output = $this->render('<mindfula11y:landmark role="banner">Header</mindfula11y:landmark>');

        self::assertStringContainsString('<header role="banner">Header</header>', $output);
    }

    #[Test]
    public function contentinfoRendersFooterWithExplicitRole(): void
    {
        $output = $this->render('<mindfula11y:landmark role="contentinfo">Footer</mindfula11y:landmark>');

        self::assertStringContainsString('<footer role="contentinfo">Footer</footer>', $output);
    }

    #[Test]
    public function navigationKeepsImplicitRole(): void
    {
        // nav maps to the navigation landmark regardless of nesting, so no
        // explicit role attribute must be emitted.
        $output = $this->render('<mindfula11y:landmark role="navigation">Links</mindfula11y:landmark>');

        self::assertStringContainsString('<nav>Links</nav>', $output);
    }

    #[Test]
    public function tagNameOverrideKeepsExplicitRole(): void
    {
        $output = $this->render('<mindfula11y:landmark role="navigation" tagName="div">Links</mindfula11y:landmark>');

        self::assertStringContainsString('<div role="navigation">Links</div>', $output);
    }

    #[Test]
    #[DataProvider('customElementNamesProvider')]
    public function tagNameOverrideRendersCustomElementNames(string $tagName): void
    {
        // The argument's contract includes integrators' own components — every
        // standards-valid custom element name must work, not only the ASCII-dash
        // convention: PCENChar also spans `.`, `_` and Unicode.
        $output = $this->render(
            '<mindfula11y:landmark tagName="{name}">Content</mindfula11y:landmark>',
            null,
            ['name' => $tagName],
        );

        self::assertStringContainsString('<' . $tagName . '>Content</' . $tagName . '>', $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function customElementNamesProvider(): array
    {
        return [
            'conventional dash name' => ['my-shell'],
            'period is a PCENChar' => ['my-widget.shell'],
            'underscore is a PCENChar' => ['my_widget-shell'],
            'non-ASCII letters are PCENChars' => ['my-élément'],
        ];
    }

    #[Test]
    public function tagNameIsUsedAsGiven(): void
    {
        // Pins the deliberate posture, so nobody re-adds an allowlist: `tagName`
        // is a template-author argument, used as written. `role` is the argument
        // that carries record data — see nonLandmarkRoleNeverReachesTheMarkup().
        $output = $this->render(
            '<mindfula11y:landmark tagName="{name}">Content</mindfula11y:landmark>',
            null,
            ['name' => 'aside'],
        );

        self::assertStringContainsString('<aside>Content</aside>', $output);
    }

    #[Test]
    public function genericOverrideWithoutARoleDropsTheAccessibleName(): void
    {
        // A generic container conveys no landmark, so keeping the accessible
        // name would label an element assistive technology cannot expose it on.
        $output = $this->render(
            '<mindfula11y:landmark tagName="span" aria="{label: \'Sidebar\'}">Content</mindfula11y:landmark>',
        );

        self::assertStringContainsString('<span>Content</span>', $output);
        self::assertStringNotContainsString('aria-label', $output);
    }

    #[Test]
    public function headerOverrideGetsAnExplicitBannerRole(): void
    {
        // header exposes banner ONLY at the top level; content elements render
        // inside main/section, so without an explicit role the element is
        // generic and the accessible name below would label nothing.
        $output = $this->render('<mindfula11y:landmark tagName="header" aria="{label: \'Article header\'}">C</mindfula11y:landmark>');

        self::assertStringContainsString('role="banner"', $output);
        self::assertStringContainsString('aria-label="Article header"', $output);
    }

    #[Test]
    public function footerOverrideGetsAnExplicitContentinfoRole(): void
    {
        $output = $this->render('<mindfula11y:landmark tagName="footer">C</mindfula11y:landmark>');

        self::assertStringContainsString('<footer role="contentinfo">C</footer>', $output);
    }

    #[Test]
    #[DataProvider('nonLandmarkRolesProvider')]
    public function nonLandmarkRoleNeverReachesTheMarkup(string $role): void
    {
        // The documented binding feeds this argument straight from a record
        // column, and DataHandler does NOT validate a static-items select
        // against its declared items (see checkValueForGroupFolderSelect: the
        // source itself notes the missing check), so an editor can store any
        // string there via tce_db. Rendering is therefore the only layer that
        // sees every write path — FormEngine, AJAX, CLI and imports alike.
        $output = $this->render(
            '<mindfula11y:landmark role="{role}">C</mindfula11y:landmark>',
            null,
            ['role' => $role],
        );

        self::assertStringContainsString('<div>C</div>', $output);
        self::assertStringNotContainsString('role=', $output);
    }

    #[Test]
    #[DataProvider('nonLandmarkRolesProvider')]
    public function nonLandmarkRoleIsDroppedEvenWithATagNameOverride(string $role): void
    {
        // The override branch must agree with the role-derived one: a stale
        // record value like "presentation" would otherwise reach the markup and
        // strip the element out of the accessibility tree, with the outcome
        // hinging on whether the template happened to pass tagName.
        $output = $this->render(
            '<mindfula11y:landmark role="{role}" tagName="div">C</mindfula11y:landmark>',
            null,
            ['role' => $role],
        );

        self::assertStringContainsString('<div>C</div>', $output);
        self::assertStringNotContainsString('role=', $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonLandmarkRolesProvider(): array
    {
        return [
            'presentation removes the element from the a11y tree' => ['presentation'],
            'none is its synonym' => ['none'],
            'an unknown value' => ['gibberish'],
        ];
    }

    #[Test]
    #[DataProvider('genericContainerProvider')]
    public function genericContainerOverridesRenderWithoutALandmarkRole(string $tagName): void
    {
        $output = $this->render(
            '<mindfula11y:landmark tagName="{name}">C</mindfula11y:landmark>',
            null,
            ['name' => $tagName],
        );

        self::assertStringContainsString('<' . $tagName . '>C</' . $tagName . '>', $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function genericContainerProvider(): array
    {
        return [
            'div' => ['div'],
            'span' => ['span'],
            'article' => ['article'],
        ];
    }

    #[Test]
    #[DataProvider('nonLandmarkOutcomeProvider')]
    public function ariaLabelIsDroppedWhenNoLandmarkSemanticsAreEmitted(string $source, array $variables): void
    {
        // The accessible name follows the RESOLVED outcome, not the raw
        // arguments: a generic element carries no landmark for the name to
        // attach to, so keeping it would only add noise to the a11y tree.
        $output = $this->render($source, null, $variables);

        self::assertStringNotContainsString('aria-label', $output);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function nonLandmarkOutcomeProvider(): array
    {
        return [
            'accepted but generic override, no role' => [
                '<mindfula11y:landmark tagName="div" aria="{label: \'Sidebar\'}">C</mindfula11y:landmark>',
                [],
            ],
            'accepted custom element, no role' => [
                '<mindfula11y:landmark tagName="my-shell" aria="{label: \'Sidebar\'}">C</mindfula11y:landmark>',
                [],
            ],
            'role that is not a landmark' => [
                '<mindfula11y:landmark role="{role}" aria="{label: \'Sidebar\'}">C</mindfula11y:landmark>',
                ['role' => 'gibberish'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('landmarkOutcomeProvider')]
    public function ariaLabelSurvivesWhenLandmarkSemanticsAreEmitted(string $source): void
    {
        // The inverse guard: an element that IS a landmark keeps its accessible
        // name — including section/form, which only become landmarks by having one.
        $output = $this->render($source);

        self::assertStringContainsString('aria-label="Sidebar"', $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function landmarkOutcomeProvider(): array
    {
        return [
            'role-derived landmark element' => ['<mindfula11y:landmark role="navigation" aria="{label: \'Sidebar\'}">C</mindfula11y:landmark>'],
            'override carries the landmark implicitly' => ['<mindfula11y:landmark tagName="nav" aria="{label: \'Sidebar\'}">C</mindfula11y:landmark>'],
            'section is a landmark once named' => ['<mindfula11y:landmark tagName="section" aria="{label: \'Sidebar\'}">C</mindfula11y:landmark>'],
            'explicit role on a generic override' => ['<mindfula11y:landmark role="navigation" tagName="div" aria="{label: \'Sidebar\'}">C</mindfula11y:landmark>'],
        ];
    }

    #[Test]
    #[DataProvider('emptyContentTagNamesProvider')]
    public function emptyChildrenStillEmitAClosingTag(string $source, string $expected): void
    {
        // HTML has no self-closing syntax for these elements, so `<main />`
        // parses as an unclosed start tag and swallows every following sibling.
        $output = $this->render($source);

        self::assertStringContainsString($expected, $output);
        self::assertStringNotContainsString('/>', $output);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function emptyContentTagNamesProvider(): array
    {
        return [
            'role-derived element' => ['<mindfula11y:landmark role="main"></mindfula11y:landmark>', '<main></main>'],
            'custom element override' => ['<mindfula11y:landmark tagName="my-shell"></mindfula11y:landmark>', '<my-shell></my-shell>'],
        ];
    }

    #[Test]
    public function uppercaseCustomElementIsLowercasedInBothTags(): void
    {
        $output = $this->render(
            '<mindfula11y:landmark tagName="{name}">C</mindfula11y:landmark>',
            null,
            ['name' => 'MY-SHELL'],
        );

        self::assertStringContainsString('<my-shell>C</my-shell>', $output);
        self::assertStringNotContainsString('MY-SHELL', $output);
    }

    #[Test]
    public function ticketlessFrontendRequestEmitsNoAnalysisAnnotations(): void
    {
        // The ticket attribute is the sole gate for analysis annotations: a
        // regular frontend request must yield clean public markup even with
        // record coordinates configured, or record uids and column names
        // would leak to every visitor.
        $output = $this->render(
            '<mindfula11y:landmark role="navigation" recordUid="102">Links</mindfula11y:landmark>',
            new ServerRequest('https://frontend.example/'),
        );

        self::assertStringContainsString('<nav>Links</nav>', $output);
        self::assertStringNotContainsString('data-mindfula11y-', $output);
    }

    #[Test]
    public function structureAnalysisRequestAnnotatesProvidedRecordUid(): void
    {
        $output = $this->render(
            '<mindfula11y:landmark role="navigation" recordUid="102">Links</mindfula11y:landmark>',
            $this->structureAnalysisRequest(),
        );

        self::assertStringContainsString('data-mindfula11y-record-uid="102"', $output);
        self::assertStringNotContainsString('data-mindfula11y-record-uid="100"', $output);
    }

}
