<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Permissions\Services\PermissionService;
use Core\Themes\Exceptions\InvalidThemeManifestException;
use Core\Themes\Exceptions\ThemeNotFoundException;
use Core\Themes\Services\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ThemeController extends Controller
{
    public function __construct(
        private readonly ThemeManager $themes,
        private readonly PermissionService $permissions,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureCanView($request);

        $activeKey = $this->themes->activeKey();
        $previewKey = $this->themes->previewKey($request->session());

        $themes = $this->themes->discover()
            ->map(fn ($descriptor): array => $this->present(
                $descriptor->key,
                $activeKey,
                $previewKey,
            ))
            ->values();

        return view('admin.themes.index', [
            'themes' => $themes,
            'activeKey' => $activeKey,
            'previewKey' => $previewKey,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function show(Request $request, string $theme): View
    {
        $this->ensureCanView($request);

        $activeKey = $this->themes->activeKey();
        $previewKey = $this->themes->previewKey($request->session());

        return view('admin.themes.show', [
            'theme' => $this->present($theme, $activeKey, $previewKey),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function activate(Request $request, string $theme): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->themes->clearPreview($request->session());
            $this->themes->activate($theme);
            $this->themes->applyEffective($request->session());
        } catch (Throwable $exception) {
            return $this->failureRedirect($theme, $exception);
        }

        return redirect()
            ->route('admin.themes.show', $theme)
            ->with('status', __('Theme activated successfully.'));
    }

    public function preview(Request $request, string $theme): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->themes->preview($theme, $request->session());
            $this->themes->applyEffective($request->session());
        } catch (Throwable $exception) {
            return $this->failureRedirect($theme, $exception);
        }

        return redirect()
            ->back()
            ->with('status', __('Theme preview enabled for your session.'));
    }

    public function clearPreview(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $this->themes->clearPreview($request->session());

        return redirect()
            ->back()
            ->with('status', __('Theme preview cleared.'));
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     version: string,
     *     author: string|null,
     *     description: string|null,
     *     parent: string|null,
     *     path: string,
     *     has_views: bool,
     *     has_assets: bool,
     *     asset_entries: list<string>,
     *     views_path: string,
     *     is_active: bool,
     *     is_preview: bool,
     *     is_loaded: bool
     * }
     */
    private function present(string $key, ?string $activeKey, ?string $previewKey): array
    {
        $descriptor = $this->themes->findOrFail($key);

        return [
            'key' => $descriptor->key,
            'label' => $descriptor->label,
            'version' => $descriptor->version,
            'author' => $descriptor->author,
            'description' => $descriptor->description,
            'parent' => $descriptor->parent,
            'path' => $descriptor->path,
            'has_views' => $descriptor->hasViews(),
            'has_assets' => $descriptor->hasAssets(),
            'asset_entries' => $descriptor->assetEntryPaths(),
            'views_path' => $descriptor->viewsPath(),
            'is_active' => $activeKey !== null && $activeKey === $descriptor->key,
            'is_preview' => $previewKey !== null && $previewKey === $descriptor->key,
            'is_loaded' => $this->themes->isLoaded($descriptor->key),
        ];
    }

    private function failureRedirect(string $theme, Throwable $exception): RedirectResponse
    {
        return redirect()
            ->route('admin.themes.show', $theme)
            ->withErrors(['theme' => $this->actionErrorMessage($exception)]);
    }

    private function actionErrorMessage(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ThemeNotFoundException,
            $exception instanceof InvalidThemeManifestException => $exception->getMessage(),
            default => __('Unable to complete this theme action.'),
        };
    }

    private function ensureCanView(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->permissions->userHasPermission($user, 'themes.view')) {
            abort(403, __('You do not have permission to view themes.'));
        }
    }

    private function ensureCanManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403, __('You do not have permission to manage themes.'));
        }
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $this->permissions->userHasPermission($user, 'themes.manage');
    }
}
