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

import type { CSSResult, TemplateResult } from 'lit';
import { html, LitElement, nothing } from 'lit';
import { property } from 'lit/decorators.js';
import '@typo3/backend/element/icon-element.js';
import type { NoticeState } from '../../lib/status-render.js';
import { noticeStateIcon, renderCountBadge } from '../../lib/status-render.js';
import { baseStyles } from '../../styles/base-styles.js';
import noticeStyles from '../../styles/notice.css.js';
import componentStyles from './notice.css.js';

/**
 * Standardized block-level status notice — the callout-shaped counterpart to
 * the pill/inline chips from the shared notice pattern (styles/notice.css).
 *
 * Works from server-rendered Fluid (light DOM) and inside other components'
 * shadow roots alike; content is slotted, so links and buttons keep the
 * caller's styling. The state icon is rendered automatically and can be
 * replaced via the `icon` slot (e.g. with a spinner while loading).
 *
 * A `count` renders the extension's shared issue-count badge after the
 * message — the marker every status surface uses for "how many". It is
 * rendered here rather than slotted because slotted nodes live in the
 * caller's tree: from Fluid that is the light DOM, where none of the
 * extension's styles reach.
 *
 * Trailing content (a "View details" link, a disclosure chevron) goes into
 * the `trailing` slot so it stays after the badge.
 */
export class Notice extends LitElement {
    static override styles: CSSResult[] = [...baseStyles, noticeStyles, componentStyles];

    @property() state: NoticeState = 'info';
    /** Issue count shown as the badge; without the attribute no badge renders. */
    @property({ type: Number }) count: number | null = null;

    override render(): TemplateResult {
        return html`<div class="notice" data-state=${this.state}>
            <slot name="icon">
                <typo3-backend-icon identifier=${noticeStateIcon(this.state)} size="small"></typo3-backend-icon>
            </slot>
            <slot></slot>
            ${this.count === null ? nothing : renderCountBadge(this.state, this.count)}
            <slot name="trailing"></slot>
        </div>`;
    }
}

/*
 * Registered without the @customElement decorator: this element is both
 * imported by other components (extensionless relative URL) and loaded
 * directly via PageRenderer for server-rendered Fluid usages, where TYPO3
 * appends a cache-bust query. The two URLs evaluate as separate module
 * instances, so an unguarded define() would throw and take the importing
 * component's whole module graph down with it.
 */
if (customElements.get('mindfula11y-notice') === undefined) {
    customElements.define('mindfula11y-notice', Notice);
}

declare global {
    interface HTMLElementTagNameMap {
        'mindfula11y-notice': Notice;
    }
}
