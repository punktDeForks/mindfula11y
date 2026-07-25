/*
 * Mindful A11y extension for TYPO3 integrating accessibility tools into the backend.
 * Copyright (C) 2026  Mindful Markup, Felix Spittel
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

// @vitest-environment happy-dom

import { afterEach, describe, expect, it, vi } from 'vitest';

const { analyzeMock, clientGetMock, clientSetMock } = vi.hoisted(() => ({
    analyzeMock: vi.fn(),
    clientGetMock: vi.fn(),
    clientSetMock: vi.fn(),
}));

vi.mock('@typo3/core/lit-helper.js', () => ({
    lll: (key: string, ...args: unknown[]): string => (args.length > 0 ? `${key}: ${args.join(', ')}` : key),
}));
vi.mock('@typo3/backend/element/icon-element.js', () => ({}));
vi.mock('@typo3/backend/element/spinner-element.js', () => ({}));
vi.mock('@typo3/backend/notification.js', () => ({
    default: { error: vi.fn(), success: vi.fn() },
}));
vi.mock('@typo3/backend/storage/client.js', () => ({
    default: { get: clientGetMock, set: clientSetMock },
}));
vi.mock('../../../Resources/Private/Source/service/structure/coordinator.js', () => {
    const module = {};
    Reflect.set(module, 'StructureAnalysisCoordinator', {
        createDefault: (): { analyze: typeof analyzeMock } => ({ analyze: analyzeMock }),
    });
    return module;
});

import type { Structure } from '../../../Resources/Private/Source/element/structure/structure.js';
import '../../../Resources/Private/Source/element/structure/structure.js';
import { StructureAnalysisError } from '../../../Resources/Private/Source/lib/structure/error.js';
import type { StructureAnalysis, StructureError } from '../../../Resources/Private/Source/lib/structure/types.js';

const makeError = (nodeId: string): StructureError => ({
    key: 'mindfula11y.structure.headings.error.emptyHeadings',
    severity: 'moderate',
    nodeId,
    viewports: ['desktop'],
});

describe('Structure', () => {
    clientGetMock.mockReturnValue(null);

    afterEach(() => {
        document.body.replaceChildren();
        analyzeMock.mockReset();
        clientGetMock.mockReset();
        clientGetMock.mockReturnValue(null);
        clientSetMock.mockReset();
    });

    // Typed `Promise<Structure>`, not `HTMLElement`: callers need `.renderRoot`,
    // which only exists on the `Structure` custom-element type.
    const renderFailed = async (error: StructureAnalysisError): Promise<Structure> => {
        analyzeMock.mockRejectedValueOnce(error);
        const view = document.createElement('mindfula11y-structure');
        view.pageId = 1;
        view.hasHeadingStructureAccess = true;
        document.body.append(view);
        await view.updateComplete;
        // The analyze task rejects in a microtask after the first update;
        // yield a macrotask so the ERROR state re-render has happened.
        await new Promise((resolve) => setTimeout(resolve, 0));
        await view.updateComplete;
        return view;
    };

    it('describes an auth failure and links the page for signing in', async () => {
        const view = await renderFailed(
            new StructureAnalysisError('auth', 'requires sign-in', 401, 'https://staging.example/protected'),
        );

        expect(view.renderRoot.textContent).toContain('mindfula11y.structure.error.rendering.auth');
        const link = view.renderRoot.querySelector('a.open-page') as HTMLAnchorElement | null;
        expect(link?.getAttribute('href')).toBe('https://staging.example/protected');
        expect(link?.target).toBe('_blank');
        expect(link?.rel).toBe('noopener');
        expect(link?.textContent).toContain('mindfula11y.structure.error.rendering.openPage');
        expect(view.renderRoot.querySelector('button.retry')).not.toBeNull();
    });

    it('offers the open-page link for framing failures too, but not without a page URL', async () => {
        const withUrl = await renderFailed(
            new StructureAnalysisError('framing', 'refused', undefined, 'https://staging.example/page'),
        );
        expect(withUrl.renderRoot.querySelector('a.open-page')).not.toBeNull();
        document.body.replaceChildren();
        analyzeMock.mockReset();

        const withoutUrl = await renderFailed(new StructureAnalysisError('timeout', 'slow'));
        expect(withoutUrl.renderRoot.querySelector('a.open-page')).toBeNull();
        expect(withoutUrl.renderRoot.querySelector('button.retry')).not.toBeNull();
    });

    it('labels and emphasizes the occurrence count in the findings overview', async () => {
        const analysis: StructureAnalysis = {
            headings: { nodes: [], errors: [makeError('heading-1'), makeError('heading-2')] },
            landmarks: null,
        };
        const view = document.createElement('mindfula11y-structure');
        view.hasHeadingStructureAccess = true;
        Reflect.set(view, 'analysis', analysis);
        document.body.append(view);
        await view.updateComplete;

        const count = view.renderRoot.querySelector('.finding-count');

        expect(count?.tagName).toBe('STRONG');
        expect(count?.textContent?.trim()).toBe('mindfula11y.structure.findingCount: 2');
        expect(count?.closest('ul.findings')).not.toBeNull();
    });

    it('summarizes the analysis as a standardized status row', async () => {
        const analysis: StructureAnalysis = {
            headings: { nodes: [], errors: [makeError('heading-1'), makeError('heading-2')] },
            landmarks: null,
        };
        const view = document.createElement('mindfula11y-structure');
        view.hasHeadingStructureAccess = true;
        Reflect.set(view, 'analysis', analysis);
        document.body.append(view);
        await view.updateComplete;

        const row = view.renderRoot.querySelector('mindfula11y-notice.status-row');

        // Two moderate findings: the row states the total and is tinted by the
        // worst present impact, not by a flat warning state.
        expect(row?.getAttribute('state')).toBe('warning');
        expect(row?.textContent).toContain('mindfula11y.structure.issuesFound: 2');
        const badge = row?.querySelector('.notice.count');
        expect(badge?.textContent).toContain('2');
        // The disclosure chevron belongs to the collapsible layout only.
        expect(row?.querySelector('.chevron')).toBeNull();
    });

    it('reports a clean page as a success row', async () => {
        const analysis: StructureAnalysis = {
            headings: { nodes: [], errors: [] },
            landmarks: null,
        };
        const view = document.createElement('mindfula11y-structure');
        view.hasHeadingStructureAccess = true;
        Reflect.set(view, 'analysis', analysis);
        document.body.append(view);
        await view.updateComplete;

        const row = view.renderRoot.querySelector('mindfula11y-notice.status-row');

        expect(row?.getAttribute('state')).toBe('success');
        expect(row?.textContent).toContain('mindfula11y.structure.noIssues');
        expect(row?.querySelector('.notice.count')).toBeNull();
    });

    // Builds a rendered widget with a two-domain analysis, so both the tablist
    // and the findings pills are present and their placement can be asserted.
    const renderAnalyzed = async (collapsible: boolean): Promise<Structure> => {
        const analysis: StructureAnalysis = {
            headings: { nodes: [], errors: [makeError('heading-1')] },
            landmarks: { nodes: [], errors: [] },
        };
        const view = document.createElement('mindfula11y-structure');
        view.hasHeadingStructureAccess = true;
        view.hasLandmarkStructureAccess = true;
        view.collapsible = collapsible;
        Reflect.set(view, 'analysis', analysis);
        document.body.append(view);
        await view.updateComplete;
        return view;
    };

    it('keeps the module layout uncollapsed with the tablist above the row', async () => {
        const view = await renderAnalyzed(false);

        expect(view.renderRoot.querySelector('details')).toBeNull();
        const tablist = view.renderRoot.querySelector('[role="tablist"]');
        const row = view.renderRoot.querySelector('mindfula11y-notice.status-row');
        expect(tablist).not.toBeNull();
        expect(row).not.toBeNull();
        // The tablist is rendered by the header, above the body's status row.
        expect((tablist?.compareDocumentPosition(row as Node) ?? 0) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('collapses the trees behind the status row in the page module', async () => {
        const view = await renderAnalyzed(true);

        const details = view.renderRoot.querySelector('details');
        expect(details?.open).toBe(false);
        // The status row is the disclosure's summary and carries the chevron.
        const row = details?.querySelector('summary > mindfula11y-notice.status-row');
        expect(row).not.toBeNull();
        expect(row?.querySelector('.chevron')?.getAttribute('identifier')).toBe('actions-chevron-right');
        // Tablist, findings pills and panels all live inside the disclosure.
        expect(details?.querySelector('[role="tablist"]')).not.toBeNull();
        expect(details?.querySelector('ul.findings')).not.toBeNull();
        expect(details?.querySelectorAll('[role="tabpanel"]').length).toBe(2);
    });

    it('drops the single-domain heading in the page module only', async () => {
        const analysis: StructureAnalysis = { headings: { nodes: [], errors: [] }, landmarks: null };
        const build = async (collapsible: boolean): Promise<Structure> => {
            const view = document.createElement('mindfula11y-structure');
            view.hasHeadingStructureAccess = true;
            view.collapsible = collapsible;
            Reflect.set(view, 'analysis', analysis);
            document.body.append(view);
            await view.updateComplete;
            return view;
        };

        // One domain, so the module renders its heading instead of a tablist.
        const module = await build(false);
        expect(module.renderRoot.querySelector('.title')?.textContent).toContain('mindfula11y.structure.headings');
        document.body.replaceChildren();

        // Collapsible mode has no header at all — the status row is the identity.
        const pageModule = await build(true);
        expect(pageModule.renderRoot.querySelector('.title')).toBeNull();
        expect(pageModule.renderRoot.querySelector('mindfula11y-notice.status-row')).not.toBeNull();
        expect(pageModule.renderRoot.querySelector('details')).not.toBeNull();
    });

    it('renders an identical status row in both surfaces for the same analysis', async () => {
        const analysis: StructureAnalysis = {
            headings: { nodes: [], errors: [makeError('heading-1'), makeError('heading-2')] },
            landmarks: null,
        };
        const build = async (collapsible: boolean): Promise<Structure> => {
            const view = document.createElement('mindfula11y-structure');
            view.hasHeadingStructureAccess = true;
            view.collapsible = collapsible;
            Reflect.set(view, 'analysis', analysis);
            document.body.append(view);
            await view.updateComplete;
            return view;
        };

        const moduleView = await build(false);
        const moduleRow = moduleView.renderRoot.querySelector('mindfula11y-notice.status-row');
        document.body.replaceChildren();

        const pageView = await build(true);
        const pageRow = pageView.renderRoot.querySelector('mindfula11y-notice.status-row');

        expect(pageRow?.getAttribute('state')).toBe(moduleRow?.getAttribute('state'));
        // The row's leading <span> carries the label; badges are the .notice.count
        // spans that follow it — compare each independently of the trailing
        // chevron, which is collapsible-only and not part of this parity claim.
        expect(pageRow?.querySelector('span')?.textContent?.trim()).toBe(
            moduleRow?.querySelector('span')?.textContent?.trim(),
        );
        const badgeText = (row: Element | null): (string | undefined)[] =>
            Array.from(row?.querySelectorAll('.notice.count') ?? []).map((badge) => badge.textContent?.trim());
        expect(badgeText(pageRow)).toEqual(badgeText(moduleRow));
    });

    it('shows a compact spinner row while the first analysis runs', async () => {
        analyzeMock.mockReturnValueOnce(new Promise(() => {}));
        const view = document.createElement('mindfula11y-structure');
        view.pageId = 1;
        view.hasHeadingStructureAccess = true;
        view.collapsible = true;
        document.body.append(view);
        await view.updateComplete;

        expect(view.renderRoot.querySelector('details')).toBeNull();
        expect(view.renderRoot.querySelector('.placeholder')).toBeNull();
        const row = view.renderRoot.querySelector('mindfula11y-notice[state="info"]');
        expect(row?.textContent).toContain('mindfula11y.structure.analyzing');
        expect(row?.querySelector('typo3-backend-spinner')).not.toBeNull();
    });

    it('restores a remembered expansion and persists a toggle', async () => {
        clientGetMock.mockReturnValue('1');
        const view = await renderAnalyzed(true);

        const details = view.renderRoot.querySelector('details');
        expect(clientGetMock).toHaveBeenCalledWith('mindfula11y-structure-expanded');
        expect(details?.open).toBe(true);
        expect(details?.querySelector('.chevron')?.getAttribute('identifier')).toBe('actions-chevron-down');

        // happy-dom does not implement summary activation; drive the native
        // state change the way the browser would report it.
        (details as HTMLDetailsElement).open = false;
        details?.dispatchEvent(new Event('toggle'));
        await view.updateComplete;

        expect(clientSetMock).toHaveBeenCalledWith('mindfula11y-structure-expanded', '0');
    });

    it('defaults to collapsed when nothing is stored', async () => {
        const view = await renderAnalyzed(true);

        expect(view.renderRoot.querySelector('details')?.open).toBe(false);
        expect(clientSetMock).not.toHaveBeenCalled();
    });

    it('keeps an open disclosure open and mounted across a re-analysis', async () => {
        clientGetMock.mockReturnValue('1');
        const view = await renderAnalyzed(true);

        const details = view.renderRoot.querySelector('details');
        expect(details?.open).toBe(true);

        // A save-triggered re-analysis swaps `analysis` in place; the
        // disclosure must neither slam shut nor be torn down and recreated.
        const nextAnalysis: StructureAnalysis = {
            headings: { nodes: [], errors: [] },
            landmarks: { nodes: [], errors: [] },
        };
        Reflect.set(view, 'analysis', nextAnalysis);
        await view.updateComplete;

        const detailsAfter = view.renderRoot.querySelector('details');
        expect(detailsAfter).toBe(details);
        expect(detailsAfter?.open).toBe(true);
    });
});
