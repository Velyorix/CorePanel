import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

function themesRoot() {
    const configured = process.env.COREPANEL_THEMES_PATH;

    if (typeof configured === 'string' && configured.trim() !== '') {
        return path.resolve(configured);
    }

    return path.join(projectRoot, 'Themes');
}

function conventionEntries(themeDir) {
    const entries = [];

    for (const css of ['resources/css/theme.css', 'resources/css/app.css']) {
        if (fs.existsSync(path.join(themeDir, css))) {
            entries.push(css);
            break;
        }
    }

    for (const js of ['resources/js/theme.js', 'resources/js/app.js']) {
        if (fs.existsSync(path.join(themeDir, js))) {
            entries.push(js);
            break;
        }
    }

    return entries;
}

function manifestEntries(themeDir) {
    const manifestPath = path.join(themeDir, 'theme.json');

    if (! fs.existsSync(manifestPath)) {
        return [];
    }

    let data;

    try {
        data = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    } catch {
        return [];
    }

    if (! data || typeof data !== 'object' || Array.isArray(data)) {
        return [];
    }

    const assets = data.assets;

    if (assets && typeof assets === 'object' && ! Array.isArray(assets)) {
        if (Array.isArray(assets.entries)) {
            return assets.entries.filter((entry) => typeof entry === 'string' && entry.trim() !== '');
        }
    }

    return conventionEntries(themeDir);
}

function toProjectRelative(themeDir, entry) {
    const absolute = path.join(themeDir, entry.replace(/\//g, path.sep));

    if (! fs.existsSync(absolute)) {
        return null;
    }

    return path.relative(projectRoot, absolute).split(path.sep).join('/');
}

export function discoverThemeViteEntries() {
    const root = themesRoot();

    if (! fs.existsSync(root)) {
        return [];
    }

    const entries = [];

    for (const directoryName of fs.readdirSync(root)) {
        const themeDir = path.join(root, directoryName);

        if (! fs.statSync(themeDir).isDirectory()) {
            continue;
        }

        if (! fs.existsSync(path.join(themeDir, 'theme.json'))) {
            continue;
        }

        for (const entry of manifestEntries(themeDir)) {
            const relative = toProjectRelative(themeDir, entry);

            if (relative !== null) {
                entries.push(relative);
            }
        }
    }

    return [...new Set(entries)];
}

export function coreViteEntries() {
    return ['resources/css/app.css', 'resources/js/app.js'];
}

export function allViteInputs() {
    return [...new Set([...coreViteEntries(), ...discoverThemeViteEntries()])];
}

export function resolvedViteInputs() {
    const fromEnv = process.env.COREPANEL_VITE_INPUTS;

    if (typeof fromEnv === 'string' && fromEnv.trim() !== '') {
        return [...new Set(
            fromEnv
                .split(',')
                .map((entry) => entry.trim())
                .filter(Boolean),
        )];
    }

    return allViteInputs();
}
