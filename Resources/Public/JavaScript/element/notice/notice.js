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
import { html, LitElement, nothing } from "lit";
import { property } from "lit/decorators.js";
import "@typo3/backend/element/icon-element.js";
import { noticeStateIcon, renderCountBadge } from "../../lib/status-render.js";
import { baseStyles } from "../../styles/base-styles.js";
import noticeStyles from "../../styles/notice.css.js";
import componentStyles from "./notice.css.js";
class Notice extends LitElement {
  constructor() {
    super(...arguments);
    this.state = "info";
    this.count = null;
  }
  static {
    this.styles = [...baseStyles, noticeStyles, componentStyles];
  }
  render() {
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
__decorateClass([
  property()
], Notice.prototype, "state", 2);
__decorateClass([
  property({ type: Number })
], Notice.prototype, "count", 2);
if (customElements.get("mindfula11y-notice") === void 0) {
  customElements.define("mindfula11y-notice", Notice);
}
export {
  Notice
};
