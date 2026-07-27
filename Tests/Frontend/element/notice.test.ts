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

vi.mock('@typo3/backend/element/icon-element.js', () => ({}));

import '../../../Resources/Private/Source/element/notice/notice.js';
import type { Notice } from '../../../Resources/Private/Source/element/notice/notice.js';

const render = async (attributes: Record<string, string>, children: string = ''): Promise<Notice> => {
    const notice = document.createElement('mindfula11y-notice');
    for (const [name, value] of Object.entries(attributes)) {
        notice.setAttribute(name, value);
    }
    notice.innerHTML = children;
    document.body.append(notice);
    await notice.updateComplete;
    return notice;
};

describe('Notice', () => {
    afterEach(() => {
        document.body.replaceChildren();
    });

    it('renders the shared count badge in its own shadow root', async () => {
        // The badge must not be slotted: from Fluid the caller's tree is the
        // light DOM, where none of the extension's styles reach.
        const notice = await render({ state: 'warning', count: '12' }, '<span>Missing alternative text</span>');

        const badge = notice.renderRoot.querySelector('.notice.count');

        expect(badge).not.toBeNull();
        expect(badge?.getAttribute('data-state')).toBe('warning');
        expect(badge?.getAttribute('data-variant')).toBe('pill');
        // The label names what is counted, so the number reads as-is — no
        // aria-hidden digit with screen-reader-only text beside it.
        expect(badge?.textContent?.trim()).toBe('12');
        expect(badge?.querySelector('.sr-only')).toBeNull();
    });

    it('renders no badge without a count', async () => {
        const notice = await render({ state: 'success' }, '<span>No issues found</span>');

        expect(notice.renderRoot.querySelector('.notice.count')).toBeNull();
    });

    it('places trailing content after the badge', async () => {
        const notice = await render(
            { state: 'warning', count: '3' },
            '<span>Issues found</span><a slot="trailing" href="#details">View details</a>',
        );

        const slots = Array.from(notice.renderRoot.querySelectorAll('slot')).map((slot) => slot.name);
        const badge = notice.renderRoot.querySelector('.notice.count') as Element;
        const trailing = notice.renderRoot.querySelector('slot[name="trailing"]') as HTMLSlotElement;

        expect(slots).toEqual(['icon', '', 'trailing']);
        expect(badge.compareDocumentPosition(trailing) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(trailing.assignedElements()[0]?.textContent).toBe('View details');
    });
});
