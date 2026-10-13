import { installLifecycle } from './core/lifecycle.js';

// Keep helpers local to this entry instead of exposing them to inline views.
const modules = [
    { load: () => import('./components/navigation') },
    { load: () => import('./components/password') },
    { load: () => import('./components/form-submit') },
    { load: () => import('./components/dialog') },
    {
        matches: root => Boolean(root.querySelector('[data-page="statistics"]')),
        load: () => import('./pages/statistics'),
    },
];

export function boot() {
    return installLifecycle(modules);
}
