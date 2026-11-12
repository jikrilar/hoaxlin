import test from 'node:test';
import assert from 'node:assert/strict';
import { mountTabs } from '../../resources/js/components/tabs.js';

globalThis.CSS = { escape: value => value };

function fixture() {
    const listeners = new Map();
    const node = (attributes = {}) => {
        const own = new Map(Object.entries(attributes));
        return {
            hidden: false, tabIndex: 0, focused: false, clickListeners: [],
            dataset: {},
            getAttribute: name => own.get(name) ?? null,
            setAttribute: (name, value) => own.set(name, String(value)),
            focus() {
                this.focused = true;
                for (const other of value('tabs')) other.focused = other === this;
            },
            clickListeners: [], keydownListeners: [],
        };
    };
    let tabsRef = [];
    const value = name => name === 'tabs' ? tabsRef : [];
    const tab = (key, panelId) => node({ role: 'tab', 'aria-controls': panelId, 'aria-selected': 'false' });
    const tabs = [tab('teks', 'panel-teks'), tab('url', 'panel-url'), tab('video', 'panel-video')];
    tabsRef = tabs;
    tabs.forEach((tabNode, index) => { tabNode.dataset.tabKey = ['teks', 'url', 'video'][index]; });
    tabs[0].setAttribute('aria-selected', 'true');
    const panels = [node(), node(), node()];
    const tablist = { querySelectorAll: () => tabs };
    const root = {
        dataset: {},
        querySelector: selector => selector === '[role="tablist"]' ? tablist : null,
        querySelectorAll: () => [],
        panel: null,
    };
    const doc = { getElementById: () => null };
    root.querySelector = selector => {
        if (selector === '[role="tablist"]') return tablist;
        const id = selector.replace(/^#/, '');
        return panels[tabs.findIndex(t => t.getAttribute('aria-controls') === id)] ?? null;
    };
    const scope = {
        listen: (target, type, handler) => { target[`${type}Listeners`].push(handler); },
        own: () => {},
    };
    mountTabs(root, scope);
    const keydown = (index, key) => {
        let prevented = false;
        const event = { key, preventDefault() { prevented = true; } };
        for (const handler of tabs[index].keydownListeners) handler(event);
        return prevented;
    };
    const click = index => {
        let prevented = false;
        for (const handler of tabs[index].clickListeners) handler({ preventDefault() { prevented = true; } });
        return prevented;
    };
    return { root, tabs, panels, keydown, click, doc };
}

test('mount marks the tablist enhanced and applies the initial selection', () => {
    const { root, tabs, panels } = fixture();
    assert.equal(root.dataset.tabsEnhanced, '');
    assert.equal(root.dataset.activeTab, 'teks');
    assert.deepEqual(tabs.map(tab => tab.tabIndex), [0, -1, -1]);
    assert.deepEqual(panels.map(panel => panel.hidden), [false, true, true]);
});

test('arrow keys, Home and End move selection and focus', () => {
    const { tabs, panels, keydown } = fixture();

    assert.equal(keydown(0, 'ArrowRight'), true);
    assert.deepEqual(tabs.map(tab => tab.getAttribute('aria-selected')), ['false', 'true', 'false']);
    assert.deepEqual(panels.map(panel => panel.hidden), [true, false, true]);
    assert.equal(tabs[1].focused, true);

    keydown(1, 'ArrowRight');
    keydown(2, 'ArrowRight');
    assert.equal(tabs[0].getAttribute('aria-selected'), 'true', 'ArrowRight wraps to the first tab');

    keydown(0, 'ArrowLeft');
    assert.equal(tabs[2].getAttribute('aria-selected'), 'true', 'ArrowLeft wraps to the last tab');

    keydown(2, 'Home');
    assert.equal(tabs[0].getAttribute('aria-selected'), 'true');

    keydown(0, 'End');
    assert.equal(tabs[2].getAttribute('aria-selected'), 'true');
});

test('unhandled keys keep native behaviour and do not change selection', () => {
    const { tabs, keydown } = fixture();
    assert.equal(keydown(0, 'Tab'), false);
    assert.equal(keydown(0, 'a'), false);
    assert.deepEqual(tabs.map(tab => tab.getAttribute('aria-selected')), ['true', 'false', 'false']);
});

test('click selects the tab and space activates the focused one', () => {
    const { tabs, panels, keydown, click } = fixture();
    assert.equal(click(2), true);
    assert.equal(tabs[2].getAttribute('aria-selected'), 'true');
    assert.deepEqual(panels.map(panel => panel.hidden), [true, true, false]);
    assert.equal(keydown(2, ' '), true);
    assert.equal(tabs[2].getAttribute('aria-selected'), 'true');
});

test('roving tabindex follows the selection so only one tab is reachable by Tab', () => {
    const { tabs, keydown } = fixture();
    keydown(0, 'End');
    assert.deepEqual(tabs.map(tab => tab.tabIndex), [-1, -1, 0]);
});

test('bundle mount enhances every tablist on the page and disposes cleanly', async () => {
    const { mount } = await import('../../resources/js/components/tabs.js');
    const created = [];
    const events = [];
    const makeTree = key => {
        const panels = [];
        const tabs = [`${key}-a`, `${key}-b`].map((name, index) => ({
            dataset: { tabKey: name }, tabIndex: 0,
            attrs: { role: 'tab', 'aria-controls': `${name}-panel`, 'aria-selected': index === 0 ? 'true' : 'false' },
            getAttribute(n) { return this.attrs[n] ?? null; },
            setAttribute(n, v) { this.attrs[n] = String(v); },
            focus() {},
            addEventListener(type, handler) { events.push([this, type, handler]); },
            removeEventListener(type, handler) {
                const at = events.findIndex(([t, y, h]) => t === this && y === type && h === handler);
                if (at !== -1) events.splice(at, 1);
            },
        }));
        const tablist = { querySelectorAll: () => tabs };
        const root = {
            dataset: {},
            querySelector: selector => {
                if (selector === '[role="tablist"]') return tablist;
                const id = selector.replace(/^#/, '');
                return panels.find(p => p.id === id) ?? null;
            },
        };
        panels.push(...tabs.map(tab => ({ id: tab.attrs['aria-controls'], hidden: false })));
        created.push(root);
        return root;
    };
    const one = makeTree('one'), two = makeTree('two');
    const page = { querySelectorAll: () => [one, two] };
    const dispose = mount(page);
    assert.ok(one.dataset.tabsEnhanced === '' && two.dataset.tabsEnhanced === '');
    assert.equal(events.length, 8, 'two tabs × click + keydown per tablist');
    dispose();
    assert.equal(events.length, 0, 'all listeners removed on dispose');
    assert.ok(one.dataset.tabsEnhanced === undefined && two.dataset.tabsEnhanced === undefined);
});
