import Alpine from 'alpinejs';
import { initTheme, registerThemeAlpine } from './theme';
import { registerProductConfiguratorPreview } from './product-configurator-preview';

initTheme();
registerThemeAlpine(Alpine);
registerProductConfiguratorPreview(Alpine);

window.Alpine = Alpine;

Alpine.start();
