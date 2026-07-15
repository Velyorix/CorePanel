import Alpine from 'alpinejs';
import { initTheme, registerThemeAlpine } from './theme';

initTheme();
registerThemeAlpine(Alpine);

window.Alpine = Alpine;

Alpine.start();
