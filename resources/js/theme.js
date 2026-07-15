const STORAGE_KEY = 'corepanel.theme';
const DEFAULT_THEME = 'system';

/**
 * @returns {'light' | 'dark' | 'system'}
 */
export function getStoredTheme() {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        if (value === 'light' || value === 'dark' || value === 'system') {
            return value;
        }
    } catch {
        // localStorage may be unavailable
    }

    return DEFAULT_THEME;
}

/**
 * @param {'light' | 'dark' | 'system'} preference
 * @returns {'light' | 'dark'}
 */
export function resolveTheme(preference) {
    if (preference === 'light' || preference === 'dark') {
        return preference;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

/**
 * @param {'light' | 'dark' | 'system'} preference
 */
export function applyTheme(preference) {
    const resolved = resolveTheme(preference);
    const root = document.documentElement;

    root.classList.toggle('dark', resolved === 'dark');
    root.dataset.theme = preference;
    root.dataset.themeResolved = resolved;
}

/**
 * @param {'light' | 'dark' | 'system'} preference
 */
export function setTheme(preference) {
    try {
        localStorage.setItem(STORAGE_KEY, preference);
    } catch {
        // ignore persistence failures
    }

    applyTheme(preference);
    window.dispatchEvent(
        new CustomEvent('theme-changed', {
            detail: { preference, resolved: resolveTheme(preference) },
        }),
    );
}

export function initTheme() {
    applyTheme(getStoredTheme());

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (getStoredTheme() === 'system') {
            applyTheme('system');
        }
    });
}

export function registerThemeAlpine(Alpine) {
    Alpine.data('themeToggle', () => ({
        preference: getStoredTheme(),
        resolved: resolveTheme(getStoredTheme()),

        init() {
            this.sync();

            window.addEventListener('theme-changed', (event) => {
                this.preference = event.detail.preference;
                this.resolved = event.detail.resolved;
            });
        },

        sync() {
            this.preference = getStoredTheme();
            this.resolved = resolveTheme(this.preference);
            applyTheme(this.preference);
        },

        /**
         * @param {'light' | 'dark' | 'system'} value
         */
        set(value) {
            setTheme(value);
            this.preference = value;
            this.resolved = resolveTheme(value);
        },

        cycle() {
            const order = ['light', 'dark', 'system'];
            const next = order[(order.indexOf(this.preference) + 1) % order.length];
            this.set(next);
        },
    }));
}
