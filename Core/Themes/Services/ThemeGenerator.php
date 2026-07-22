<?php

namespace Core\Themes\Services;

use Core\Themes\DataTransferObjects\ThemeDescriptor;
use Core\Themes\Exceptions\ThemeScaffoldException;
use Core\Themes\Support\ThemeManifestWriter;
use Core\Themes\Support\ThemeViewPath;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Generates theme views and assets inside an existing theme package.
 */
class ThemeGenerator
{
    public function __construct(
        private readonly ThemeManager $themes,
    ) {
    }

    public function resolveTheme(?string $themeKey): ThemeDescriptor
    {
        if (is_string($themeKey) && trim($themeKey) !== '') {
            return $this->themes->findOrFail(trim($themeKey));
        }

        $active = $this->themes->activeKey();

        if ($active !== null) {
            return $this->themes->findOrFail($active);
        }

        throw ThemeScaffoldException::themeRequired();
    }

    public function makeView(ThemeDescriptor $theme, string $name): string
    {
        return $this->makeBlade($theme, ThemeViewPath::normalize($name), $this->viewStub($name));
    }

    public function makeLayout(ThemeDescriptor $theme, string $name): string
    {
        $relative = ThemeViewPath::forLayout($name);
        $layoutName = trim(str_replace('\\', '/', $name), '/');

        return $this->makeBlade($theme, $relative, $this->layoutStub($layoutName));
    }

    public function makeComponent(ThemeDescriptor $theme, string $name): string
    {
        return $this->makeBlade($theme, ThemeViewPath::forComponent($name), $this->componentStub($name));
    }

    public function makePartial(ThemeDescriptor $theme, string $name): string
    {
        return $this->makeBlade($theme, ThemeViewPath::forPartial($name), $this->partialStub($name));
    }

    /**
     * @return array{path: string, manifest_updated: bool, relative_entry: string}
     */
    public function makeAsset(ThemeDescriptor $theme, string $name, string $type): array
    {
        $type = strtolower(trim($type));

        if (! in_array($type, ['css', 'js'], true)) {
            throw ThemeScaffoldException::invalidAssetType($type);
        }

        $basename = $this->normalizeAssetBasename($name, $type);
        $relativeEntry = $type === 'css'
            ? 'resources/css/'.$basename
            : 'resources/js/'.$basename;
        $absolute = $theme->path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeEntry);

        if (is_file($absolute)) {
            throw ThemeScaffoldException::fileExists($absolute);
        }

        File::ensureDirectoryExists(dirname($absolute));

        $contents = $type === 'css'
            ? $this->assetCssStub($basename)
            : $this->assetJsStub($basename);

        if (File::put($absolute, $contents) === false) {
            throw ThemeScaffoldException::writeFailed($absolute);
        }

        $manifestUpdated = ThemeManifestWriter::addAssetEntry($theme->path, $relativeEntry);
        $this->themes->discover(refresh: true);

        return [
            'path' => $absolute,
            'manifest_updated' => $manifestUpdated,
            'relative_entry' => $relativeEntry,
        ];
    }

    private function makeBlade(ThemeDescriptor $theme, string $relativeView, string $contents): string
    {
        $relativeView = str_replace('\\', '/', $relativeView);
        $absolute = $theme->path
            .DIRECTORY_SEPARATOR
            .'resources'
            .DIRECTORY_SEPARATOR
            .'views'
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $relativeView);

        if (is_file($absolute)) {
            throw ThemeScaffoldException::fileExists($absolute);
        }

        File::ensureDirectoryExists(dirname($absolute));

        if (File::put($absolute, $contents) === false) {
            throw ThemeScaffoldException::writeFailed($absolute);
        }

        return $absolute;
    }

    private function normalizeAssetBasename(string $name, string $type): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $name = preg_replace('/\.(css|js)$/', '', $name) ?? $name;

        if ($name === '' || str_contains($name, '..') || str_contains($name, '/')) {
            throw new InvalidArgumentException('Asset name is invalid.');
        }

        return $name.'.'.$type;
    }

    private function viewStub(string $name): string
    {
        $viewName = str_replace('/', '.', preg_replace('/\.blade\.(php)?$/', '', $name) ?? $name);

        return str_replace('VIEW_NAME', $viewName, <<<'BLADE'
<x-layout.admin :title="__('Page')">
    {{-- Theme view override: VIEW_NAME --}}
    <x-ui.card :title="__('Content')">
        <p class="text-muted-foreground">{{ __('Replace this stub with your themed content.') }}</p>
    </x-ui.card>
</x-layout.admin>

BLADE);
    }

    private function layoutStub(string $name): string
    {
        return str_replace('LAYOUT_NAME', $name, <<<'BLADE'
@props([
    'title' => null,
    'pageHeading' => null,
])

@php
    $pageTitle = filled($title)
        ? $title.' — '.config('corepanel.name')
        : config('corepanel.name');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle }}</title>
        <x-ui.theme-script />
        <x-ui.vite-assets />
        {{ $head ?? '' }}
        @stack('head')
    </head>
    <body class="min-h-full bg-background font-sans text-body text-foreground antialiased">
        {{-- Theme layout override: LAYOUT_NAME --}}
        {{ $slot }}
    </body>
</html>

BLADE);
    }

    private function componentStub(string $name): string
    {
        return str_replace('COMPONENT_NAME', $name, <<<'BLADE'
@props([])

<div {{ $attributes->class('') }}>
    {{-- Theme component override: COMPONENT_NAME --}}
    {{ $slot }}
</div>

BLADE);
    }

    private function partialStub(string $name): string
    {
        return str_replace('PARTIAL_NAME', $name, <<<'BLADE'
{{-- Theme partial override: PARTIAL_NAME --}}

BLADE);
    }

    private function assetCssStub(string $basename): string
    {
        return <<<CSS
/*
| Theme asset: {$basename}
| Registered in theme.json for Vite compilation.
*/

@layer theme {
    /* Add styles here */
}

CSS;
    }

    private function assetJsStub(string $basename): string
    {
        return <<<JS
/**
 * Theme asset: {$basename}
 * Registered in theme.json for Vite compilation.
 */

JS;
    }
}
