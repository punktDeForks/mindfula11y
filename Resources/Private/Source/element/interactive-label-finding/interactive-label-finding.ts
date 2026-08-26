import { lll } from '@typo3/core/lit-helper.js';
import type { CSSResult, TemplateResult } from 'lit';
import { html, LitElement, nothing } from 'lit';
import { customElement, property, state } from 'lit/decorators.js';
import { live } from 'lit/directives/live.js';
import '@typo3/backend/element/icon-element.js';
import '@typo3/backend/element/spinner-element.js';

import '../notice/notice.js';

import { renderSaveButton, renderSaveStatusRegion } from '../../lib/status-render.js';
import { RecordApi } from '../../service/record-api.js';
import { type ErrorView, errorView } from '../../service/request-error.js';
import { baseStyles } from '../../styles/base-styles.js';
import buttonStyles from '../../styles/button.css.js';
import componentStyles from './interactive-label-finding.css.js';

const REPEATED_RULE = 'repeated_generic_label';
const DIFFERENT_TARGETS_RULE = 'generic_label_different_targets';

interface InteractiveLabelFindingData {
    table?: string;
    uid?: number;
    field?: string;
    value?: string;
    /** Whether the current backend user may write this field (PermissionService-gated). */
    editable?: boolean;

    rule?: string;

    isRepeated?: boolean;
    occurrenceCount?: number;

    hasDifferentTargets?: boolean;
    distinctTargetCount?: number;
}

/**
 * Renders the notices for one interactive label finding.
 *
 * Titles and descriptions are resolved client-side via lll() from the rule
 * slug alone — no PHP-side translation step needed. The two page-wide rule
 * slugs (REPEATED_RULE / DIFFERENT_TARGETS_RULE) are known constants here,
 * so a secondary notice never needs a slug from the server either — only
 * the boolean flag and count that trigger it.
 */
@customElement('mindfula11y-interactive-label-finding')
export class InteractiveLabelFinding extends LitElement {
    static override styles: CSSResult[] = [...baseStyles, buttonStyles, componentStyles];

    @property({ type: Object })
    finding: InteractiveLabelFindingData | null = null;

    @state() private value: string = '';
    @state() private lastSavedValue: string = '';
    @state() private busy: 'idle' | 'saving' = 'idle';
    @state() private saved: boolean = false;
    @state() private actionError: ErrorView | null = null;

    private readonly recordApi = new RecordApi();

    protected override willUpdate(): void {
        if (!this.hasUpdated) {
            this.value = this.finding?.value ?? '';
            this.lastSavedValue = this.value;
        }
    }

    override render(): TemplateResult | typeof nothing {
        const finding = this.finding;

        if (finding === null) {
            return nothing;
        }

        return html`
      ${finding.editable ? this.renderEditor() : this.renderReadOnlyValue()}

      <div class="notices">
        ${this.renderPrimaryNotice(finding)}

        ${this.renderSecondaryNotice(finding, REPEATED_RULE, finding.isRepeated ?? false, finding.occurrenceCount)}

        ${this.renderSecondaryNotice(
            finding,
            DIFFERENT_TARGETS_RULE,
            finding.hasDifferentTargets ?? false,
            finding.distinctTargetCount,
        )}
      </div>

      ${finding.editable ? this.renderActions() : nothing}
    `;
    }

    private renderEditor(): TemplateResult {
        return html`<div class="editor">
            <label class="label" for="value">${lll('mindfula11y.findings.label')}</label>
            <input
                id="value"
                type="text"
                class="input"
                .value=${live(this.value)}
                ?readonly=${this.busy !== 'idle'}
                @input=${this.handleInput}
            />
        </div>`;
    }

    /** Mirrors altless-file-reference's read-only fallback for a finding the current user cannot edit. */
    private renderReadOnlyValue(): TemplateResult | typeof nothing {
        if (this.value === '') {
            return nothing;
        }

        return html`<dl class="editor">
            <dt class="label">${lll('mindfula11y.findings.label')}</dt>
            <dd class="readonly-value">${this.value}</dd>
        </dl>`;
    }

    private renderActions(): TemplateResult {
        return html`
      <div class="actions">
        ${renderSaveButton({
            saving: this.busy === 'saving',
            disabled: this.busy !== 'idle' || this.value === this.lastSavedValue,
            labelKey: 'mindfula11y.findings.save',
            onClick: () => this.handleSave(),
        })}
      </div>

      ${renderSaveStatusRegion({
          error: this.actionError,
          saved: this.saved,
          successLabelKey: 'mindfula11y.findings.save.success',
      })}
    `;
    }

    private handleInput(event: Event): void {
        this.value = (event.target as HTMLInputElement).value;
        this.saved = false;
    }

    private async handleSave(): Promise<void> {
        // Mirrors the button's aria-disabled condition: the control stays
        // focusable (a real `disabled` would blur a keyboard user to <body>
        // for the whole async window), so the click handler is the guard.
        if (this.busy !== 'idle' || this.value === this.lastSavedValue) {
            return;
        }

        const finding = this.finding;

        if (
            finding?.editable !== true ||
            finding.table === undefined ||
            finding.field === undefined ||
            finding.uid === undefined
        ) {
            return;
        }

        this.busy = 'saving';
        this.actionError = null;
        try {
            await this.recordApi.updateFields(finding.table, finding.uid, { [finding.field]: this.value });
            this.lastSavedValue = this.value;
            this.saved = true;
        } catch (error) {
            this.actionError = errorView(error, 'mindfula11y.findings.save.error');
        } finally {
            this.busy = 'idle';
        }
    }

    private renderPrimaryNotice(finding: InteractiveLabelFindingData): TemplateResult | typeof nothing {
        if (!finding.rule) {
            return nothing;
        }

        let count: number | undefined;

        if (finding.rule === REPEATED_RULE) {
            count = finding.occurrenceCount;
        } else if (finding.rule === DIFFERENT_TARGETS_RULE) {
            count = finding.distinctTargetCount;
        }

        return this.renderNoticeForRule(finding.rule, count);
    }

    /**
     * A page-wide issue is rendered separately only when it is not already
     * the primary issue of this finding (avoids reporting it twice).
     */
    private renderSecondaryNotice(
        finding: InteractiveLabelFindingData,
        ruleSlug: string,
        isActive: boolean,
        count?: number,
    ): TemplateResult | typeof nothing {
        if (!isActive || finding.rule === ruleSlug) {
            return nothing;
        }

        return this.renderNoticeForRule(ruleSlug, count);
    }

    private renderNoticeForRule(ruleSlug: string, count?: number): TemplateResult | typeof nothing {
        const ruleTitleKey = `mindfula11y.findings.rule.title.${ruleSlug}`;

        const ruleDescriptionKey = `mindfula11y.findings.rule.description.${ruleSlug}`;

        const title = lll(ruleTitleKey);
        const description = lll(ruleDescriptionKey);

        if (!title && !description) {
            return nothing;
        }

        return html`
      <mindfula11y-notice
        state="warning"
        count=${count ?? nothing}
      >
        <span>
          ${title ? html`<strong>${title}</strong>` : nothing}

          ${title && description ? html`<br />` : nothing}

          ${description || nothing}
        </span>
      </mindfula11y-notice>
    `;
    }
}

declare global {
    interface HTMLElementTagNameMap {
        'mindfula11y-interactive-label-finding': InteractiveLabelFinding;
    }
}
