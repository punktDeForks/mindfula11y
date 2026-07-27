import { html, nothing } from "lit";
const renderTablist = (opts) => {
  const { ariaLabel, tabs, activeTab, onSelect, onKeydown } = opts;
  return html`<div class="tabs" role="tablist" aria-label=${ariaLabel}>
        ${tabs.map((tab) => {
    const selected = activeTab === tab.id;
    return html`<button
                type="button"
                role="tab"
                id="tab-${tab.id}"
                data-tab=${tab.id}
                aria-selected=${selected ? "true" : "false"}
                aria-controls="panel-${tab.id}"
                tabindex=${selected ? "0" : "-1"}
                aria-disabled=${tab.disabled ?? false ? "true" : nothing}
                @click=${() => {
      if (tab.disabled !== true) {
        onSelect(tab.id);
      }
    }}
                @keydown=${onKeydown}
            >
                ${tab.label} ${tab.badge ?? nothing}
            </button>`;
  })}
    </div>`;
};
const untilFoundSupported = () => "onbeforematch" in HTMLElement.prototype;
const renderTabPanel = (opts) => {
  const { tab, active, busy, content } = opts;
  if (!opts.withTablist) {
    return html`<div class="panel" role="region" aria-label=${opts.label} aria-busy=${busy ? "true" : nothing}>
            ${content}
        </div>`;
  }
  return html`<div
        class="panel"
        role="tabpanel"
        id="panel-${tab}"
        aria-labelledby="tab-${tab}"
        tabindex=${active ? "0" : nothing}
        aria-busy=${busy ? "true" : nothing}
        hidden=${active ? nothing : untilFoundSupported() ? "until-found" : ""}
        aria-hidden=${active ? nothing : "true"}
        @beforematch=${opts.onReveal}
    >
        ${content}
    </div>`;
};
async function activateTabFromKeydown(host, event, tabs, activeTab, activate) {
  if (event.key !== "ArrowRight" && event.key !== "ArrowLeft" && event.key !== "Home" && event.key !== "End") {
    return;
  }
  const enabled = tabs.filter(
    (tab) => host.renderRoot.querySelector(`[data-tab="${tab}"]`)?.getAttribute("aria-disabled") !== "true"
  );
  if (enabled.length === 0) {
    return;
  }
  const from = tabs.indexOf(activeTab);
  const nearestEnabled = (direction) => {
    for (let step = 1; step <= tabs.length; step++) {
      const index = ((from + direction * step) % tabs.length + tabs.length) % tabs.length;
      const candidate = tabs[index];
      if (candidate !== void 0 && enabled.includes(candidate)) {
        return candidate;
      }
    }
    return void 0;
  };
  let next;
  switch (event.key) {
    case "ArrowRight":
      next = nearestEnabled(1);
      break;
    case "ArrowLeft":
      next = nearestEnabled(-1);
      break;
    case "Home":
      next = enabled[0];
      break;
    case "End":
      next = enabled[enabled.length - 1];
      break;
    default:
      return;
  }
  if (next === void 0) {
    return;
  }
  event.preventDefault();
  activate(next);
  await host.updateComplete;
  host.renderRoot.querySelector(`[data-tab="${next}"]`)?.focus();
}
class TabsController {
  constructor(host, tabs, initial) {
    this.host = host;
    this.tabs = tabs;
    this.handleKeydown = (event) => {
      void activateTabFromKeydown(this.host, event, this.tabs(), this.active, (tab) => this.select(tab));
    };
    this.active = initial;
    host.addController(this);
  }
  hostConnected() {
  }
  get activeTab() {
    return this.active;
  }
  /** Activates a tab and re-renders the host (click selection, findings jump). */
  select(tab) {
    this.active = tab;
    this.host.requestUpdate();
  }
  /**
   * Re-anchors the active tab when it is no longer available. Call from
   * `willUpdate` — the host is already updating, so no update is requested.
   */
  ensureActive(fallback) {
    const available = this.tabs();
    if (!available.includes(this.active)) {
      this.active = available[0] ?? fallback;
    }
  }
  /**
   * Whether the container shows tab chrome at all. Derived from the tab set
   * the controller already owns, so the rule lives here rather than at every
   * call site: a lone tab has nothing to switch between, and its panel names
   * itself as a region instead of being named by an absent tab.
   */
  get withTablist() {
    return this.tabs().length > 1;
  }
  /**
   * Renders the tablist for the host-built descriptors of the current tab
   * set, or nothing when a single tab makes the chrome pointless.
   */
  renderTablist(opts) {
    if (!this.withTablist) {
      return nothing;
    }
    return renderTablist({
      ...opts,
      activeTab: this.active,
      onSelect: (id) => this.select(id),
      onKeydown: this.handleKeydown
    });
  }
  /** Renders one panel wrapper around the host-supplied content. */
  renderPanel(opts) {
    return renderTabPanel({
      ...opts,
      withTablist: this.withTablist,
      active: this.active === opts.tab,
      onReveal: () => {
        const button = this.host.renderRoot.querySelector(`[data-tab="${opts.tab}"]`);
        if (button?.getAttribute("aria-disabled") !== "true") {
          this.select(opts.tab);
        }
      }
    });
  }
}
export {
  TabsController,
  activateTabFromKeydown,
  renderTabPanel,
  renderTablist
};
