<?php

namespace Core\Themes\Services;

use Core\Themes\DataTransferObjects\ThemeDescriptor;
use Core\Themes\Exceptions\ThemeScaffoldException;
use Core\Themes\Support\ThemeName;
use Illuminate\Support\Facades\File;

/**
 * Creates new theme package scaffolds on disk.
 */
class ThemeScaffolder
{
    public function __construct(
        private readonly ThemeManager $themes,
        private readonly ?string $themesPath = null,
    ) {
    }

    /**
     * @param  array{
     *     key?: string|null,
     *     label?: string|null,
     *     author?: string|null,
     *     description?: string|null,
     *     version?: string|null
     * }  $options
     * @return array{descriptor: ThemeDescriptor, path: string, files: list<string>}
     */
    public function make(string $name, array $options = []): array
    {
        $parsed = ThemeName::fromInput(
            $name,
            $options['key'] ?? null,
            $options['label'] ?? null,
        );

        $root = $this->path().DIRECTORY_SEPARATOR.$parsed->directory;

        if (is_dir($root)) {
            throw ThemeScaffoldException::directoryExists($root);
        }

        $this->themes->discover(refresh: true);

        if ($this->themes->has($parsed->key)) {
            throw ThemeScaffoldException::keyExists($parsed->key);
        }

        $author = isset($options['author']) && is_string($options['author'])
            ? trim($options['author'])
            : config('app.name', 'CorePanel');
        $description = isset($options['description']) && is_string($options['description'])
            ? trim($options['description'])
            : "Theme package {$parsed->label}.";
        $version = isset($options['version']) && is_string($options['version'])
            ? trim($options['version'])
            : '1.0.0';

        if ($author === '') {
            $author = config('app.name', 'CorePanel');
        }

        if ($description === '') {
            $description = "Theme package {$parsed->label}.";
        }

        if ($version === '') {
            $version = '1.0.0';
        }

        $written = [];

        File::ensureDirectoryExists($root);
        File::ensureDirectoryExists($root.'/resources/views');
        File::ensureDirectoryExists($root.'/resources/css');
        File::ensureDirectoryExists($root.'/resources/js');

        $written[] = $this->write($root.'/theme.json', $this->manifest(
            key: $parsed->key,
            label: $parsed->label,
            version: $version,
            author: $author,
            description: $description,
        ));
        $written[] = $this->write($root.'/README.md', $this->readme($parsed, $author));
        $written[] = $this->write($root.'/resources/css/theme.css', $this->themeCss($parsed->label));
        $written[] = $this->write($root.'/resources/js/theme.js', $this->themeJs($parsed->label));
        $written[] = $this->write($root.'/resources/views/.gitkeep', '');

        $descriptor = ThemeDescriptor::fromDirectory($root, $parsed->directory);
        $this->themes->discover(refresh: true);

        return [
            'descriptor' => $descriptor,
            'path' => $root,
            'files' => $written,
        ];
    }

    private function path(): string
    {
        $configured = $this->themesPath ?? config('corepanel.themes.path');

        if (! is_string($configured) || $configured === '') {
            return base_path('Themes');
        }

        return $configured;
    }

    private function write(string $path, string $contents): string
    {
        if (File::put($path, $contents) === false) {
            throw ThemeScaffoldException::writeFailed($path);
        }

        return $path;
    }

    private function manifest(
        string $key,
        string $label,
        string $version,
        string $author,
        string $description,
    ): string {
        $payload = [
            'name' => $key,
            'label' => $label,
            'version' => $version,
            'author' => $author,
            'description' => $description,
            'assets' => [
                'entries' => [
                    'resources/css/theme.css',
                    'resources/js/theme.js',
                ],
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    private function readme(ThemeName $name, string $author): string
    {
        return <<<MD
# {$name->label}

Theme package for CorePanel (key: `{$name->key}`).

## Structure

```txt
{$name->directory}/
├── theme.json
├── README.md
├── resources/
│   ├── css/
│   │   └── theme.css
│   ├── js/
│   │   └── theme.js
│   └── views/
```

## Development

1. Add Blade overrides under `resources/views/` using the same paths as core views.
2. Extend styles in `resources/css/theme.css` (loaded after the core bundle).
3. Run `npm run build` or `npm run dev` so Vite picks up the theme asset entries.

## Activation

Use **Admin → Themes** or `theme:activate {$name->key}` once available.

MD;
    }

    private function themeCss(string $label): string
    {
        return <<<CSS
/*
| {$label} — theme overrides
| Loaded after the core stylesheet via Vite.
*/

@layer theme {
    /* Add theme-specific CSS here */
}

CSS;
    }

    private function themeJs(string $label): string
    {
        return <<<JS
/**
 * {$label} — theme JavaScript
 * Loaded after the core application bundle.
 */

JS;
    }
}
