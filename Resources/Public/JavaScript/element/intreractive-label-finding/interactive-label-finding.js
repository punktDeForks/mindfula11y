var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __decorateClass = (decorators, target, key, kind) => {
  var result = kind > 1 ? void 0 : kind ? __getOwnPropDesc(target, key) : target;
  for (var i = decorators.length - 1, decorator; i >= 0; i--)
    if (decorator = decorators[i])
      result = (kind ? decorator(target, key, result) : decorator(result)) || result;
  if (kind && result) __defProp(target, key, result);
  return result;
};
import { lll } from "@typo3/core/lit-helper.js";
import { html, LitElement, nothing } from "lit";
import { customElement, property } from "lit/decorators.js";
import "../notice/notice.js";
import { baseStyles } from "../../styles/base-styles.js";
import componentStyles from "./interactive-label-finding.css.js";
const REPEATED_RULE = "repeated_generic_label";
const DIFFERENT_TARGETS_RULE = "generic_label_different_targets";
let InteractiveLabelFinding = class extends LitElement {
  constructor() {
    super(...arguments);
    this.finding = null;
  }
  render() {
    const finding = this.finding;
    if (finding === null) {
      return nothing;
    }
    return html`
            <div class="notices">
                ${this.renderPrimaryNotice(finding)}

                ${this.renderSecondaryNotice(
      finding,
      REPEATED_RULE,
      finding.isRepeated ?? false,
      finding.occurrenceCount
    )}

                ${this.renderSecondaryNotice(
      finding,
      DIFFERENT_TARGETS_RULE,
      finding.hasDifferentTargets ?? false,
      finding.distinctTargetCount
    )}
            </div>
        `;
  }
  renderPrimaryNotice(finding) {
    if (!finding.rule) {
      return nothing;
    }
    let count;
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
  renderSecondaryNotice(finding, ruleSlug, isActive, count) {
    if (!isActive || finding.rule === ruleSlug) {
      return nothing;
    }
    return this.renderNoticeForRule(ruleSlug, count);
  }
  renderNoticeForRule(ruleSlug, count) {
    const title = lll(`mindfula11y.findings.rule.title.${ruleSlug}`);
    const description = lll(`mindfula11y.findings.rule.description.${ruleSlug}`);
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
};
InteractiveLabelFinding.styles = [
  ...baseStyles,
  componentStyles
];
__decorateClass([
  property({ type: Object })
], InteractiveLabelFinding.prototype, "finding", 2);
InteractiveLabelFinding = __decorateClass([
  customElement("mindfula11y-interactive-label-finding")
], InteractiveLabelFinding);
export {
  InteractiveLabelFinding
};
