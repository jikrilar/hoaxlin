/**
 * Accessible tablist behaviour shared by <x-ui.tabs>.
 *
 * Markup owns the semantics (role=tablist/tab/tabpanel, aria-selected, tabindex);
 * this module only switches state and implements roving focus. Without JS the
 * component degrades to labelled sections, so nothing is hidden by default.
 */

import { defineModule } from '../core/scope.js';

const KEYS = {
    ArrowRight: (index, count) => (index + 1) % count,
    ArrowLeft: (index, count) => (index - 1 + count) % count,
    Home: () => 0,
    End: (index, count) => count - 1,
};

export function mountTabs(container, scope) {
    const tablist = container.querySelector('[role="tablist"]');
    if (!tablist) return;
    const tabs = [...tablist.querySelectorAll('[role="tab"]')];
    if (!tabs.length) return;
    const panels = tabs.map(tab => {
        const id = tab.getAttribute('aria-controls');
        return id ? container.querySelector(`#${CSS.escape(id)}`) : null;
    });

    const activate = index => {
        tabs.forEach((tab, position) => {
            const selected = position === index;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            const panel = panels[position];
            if (panel) panel.hidden = !selected;
        });
        const key = tabs[index].dataset.tabKey ?? String(index);
        container.dataset.activeTab = key;
        // Consumers (single-form submission) keep their own state in sync.
        container.dispatchEvent(new CustomEvent('tabchange', { detail: { key }, bubbles: true }));
    };

    const current = () => Math.max(0, tabs.findIndex(tab => tab.getAttribute('aria-selected') === 'true'));
    const focusTab = index => {
        activate(index);
        tabs[index].focus();
    };

    tabs.forEach(tab => {
        scope.listen(tab, 'click', event => {
            event.preventDefault();
            focusTab(tabs.indexOf(tab));
        });
        scope.listen(tab, 'keydown', event => {
            if (event.key === ' ') { event.preventDefault(); focusTab(tabs.indexOf(tab)); return; }
            const next = KEYS[event.key];
            if (!next) return;
            event.preventDefault();
            focusTab(next(tabs.indexOf(tab), tabs.length));
        });
    });
    // Re-apply the server-rendered selection so panels and roving tabindex agree.
    activate(current());
    container.dataset.tabsEnhanced = '';
    scope.own(() => { delete container.dataset.tabsEnhanced; });
}

export const mount = defineModule((page, scope) => {
    for (const container of page.querySelectorAll('[data-tabs]')) {
        // Only report the enhanced flag for containers this module actually wired.
        const tablist = container.querySelector('[role="tablist"]');
        if (!tablist || !tablist.querySelectorAll('[role="tab"]').length) continue;
        mountTabs(container, scope);
    }
});
