/**
 * @param {{ previewUrl: string, initial: Record<string, string|number|boolean|null> }} config
 */
export function registerProductConfiguratorPreview(Alpine) {
    Alpine.data('productConfiguratorPreview', (config) => ({
        previewUrl: config.previewUrl,
        loading: false,
        error: null,
        preview: config.initial ?? {},
        debounceTimer: null,

        init() {
            this.refresh();

            this.$el.addEventListener('change', () => this.scheduleRefresh());
            this.$el.addEventListener('input', (event) => {
                if (event.target?.matches('input[type="number"], input[type="text"]')) {
                    this.scheduleRefresh();
                }
            });
        },

        scheduleRefresh() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.refresh(), 250);
        },

        async refresh() {
            this.loading = true;
            this.error = null;

            try {
                const form = this.$el;
                const body = new FormData(form);
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                const response = await fetch(this.previewUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body,
                    credentials: 'same-origin',
                });

                const payload = await response.json();

                if (! response.ok) {
                    this.error = payload.message
                        || payload.errors?.configurator?.[0]
                        || payload.errors?.billing_cycle?.[0]
                        || 'Unable to calculate price.';
                    return;
                }

                this.preview = payload;
            } catch {
                this.error = 'Unable to calculate price.';
            } finally {
                this.loading = false;
            }
        },
    }));
}
