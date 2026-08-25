import { lll } from '@typo3/core/lit-helper.js';
import type { CSSResult, TemplateResult } from 'lit';
import { html, LitElement, nothing } from 'lit';
import { customElement, property } from 'lit/decorators.js';

import '../notice/notice.js';

import { baseStyles } from '../../styles/base-styles.js';
import componentStyles from './interactive-label-finding.css.js';

const REPEATED_RULE = 'repeated_generic_label';
const DIFFERENT_TARGETS_RULE = 'generic_label_different_targets';

interface InteractiveLabelFindingData {
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
    static override styles: CSSResult[] = [...baseStyles, componentStyles];

    @property({ type: Object })
    finding: InteractiveLabelFindingData | null = null;

    override render(): TemplateResult | typeof nothing {
        const finding = this.finding;

        if (finding === null) {
            return nothing;
        }

        return html`
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
    `;
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
