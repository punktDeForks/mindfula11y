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

import type { NoticeState } from '../status-render.js';
import type { ScanResult } from './types.js';
import { ScanStatus } from './types.js';

/** How a scan result's status presents on a `.notice` surface. */
export interface ScanStatusView {
    state: NoticeState;
    /** XLF label key; callers localize via `lll(labelKey)`. */
    labelKey: string;
    /**
     * Label key for a live region, where the visible row's split into label +
     * count badge does not survive: a spoken "accessibility issues found"
     * without the number is useless, and two scans differing only in count
     * would announce identical text and be suppressed as a repeat. Set
     * together with `count` and localized as `lll(announceLabelKey, count)`;
     * without it the visible label is what gets announced.
     */
    announceLabelKey?: string;
    /** Issue total, rendered as the notice's shared count badge. */
    count?: number;
    /** In-progress statuses show a spinner instead of the state icon. */
    spinner?: boolean;
}

/**
 * The single ScanStatus → presentation mapping shared by the scan module and
 * the compact issue-count callout. Pure: no Lit, no localization — the caller
 * renders the notice and localizes the key (and may layer progress detail on
 * top of the in-progress states).
 */
export function scanStatusView(result: ScanResult): ScanStatusView {
    switch (result.status) {
        case ScanStatus.Pending:
            return { state: 'info', labelKey: 'mindfula11y.scan.status.pending', spinner: true };
        case ScanStatus.Running:
            return { state: 'info', labelKey: 'mindfula11y.scan.status.running', spinner: true };
        case ScanStatus.Analyzing:
            return { state: 'info', labelKey: 'mindfula11y.scan.status.analyzing', spinner: true };
        case ScanStatus.Failed:
            return { state: 'danger', labelKey: 'mindfula11y.scan.status.failed' };
        case ScanStatus.Canceled:
            return { state: 'info', labelKey: 'mindfula11y.scan.status.canceled' };
        default:
            return result.totalIssueCount > 0
                ? {
                      state: 'warning',
                      labelKey: 'mindfula11y.scan.issuesFound',
                      announceLabelKey: 'mindfula11y.scan.announce.issuesFound',
                      count: result.totalIssueCount,
                  }
                : { state: 'success', labelKey: 'mindfula11y.scan.noIssues' };
    }
}
