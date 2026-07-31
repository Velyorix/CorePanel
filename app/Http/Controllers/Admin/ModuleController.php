<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateModuleConfigRequest;
use Core\Modules\Enums\ModuleCapability;
use Core\Modules\Exceptions\ModuleBootstrapException;
use Core\Modules\Exceptions\ModuleNotFoundException;
use Core\Modules\Exceptions\ModuleSignatureException;
use Core\Modules\Services\ModuleManager;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly PermissionService $permissions,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureCanView($request);

        $modules = $this->modules->discover()
            ->map(fn ($manifest): array => $this->present($manifest->key))
            ->values();

        return view('admin.modules.index', [
            'modules' => $modules,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function show(Request $request, string $module): View
    {
        $this->ensureCanView($request);

        return view('admin.modules.show', [
            'module' => $this->present($module),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function install(Request $request, string $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->modules->install($module);
        } catch (Throwable $exception) {
            return $this->failureRedirect($module, $exception);
        }

        return redirect()
            ->route('admin.modules.show', $module)
            ->with('status', __('Module installed successfully.'));
    }

    public function enable(Request $request, string $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->modules->enable($module);
        } catch (Throwable $exception) {
            return $this->failureRedirect($module, $exception);
        }

        return redirect()
            ->route('admin.modules.show', $module)
            ->with('status', __('Module enabled successfully.'));
    }

    public function disable(Request $request, string $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->modules->disable($module);
        } catch (Throwable $exception) {
            return $this->failureRedirect($module, $exception);
        }

        return redirect()
            ->route('admin.modules.show', $module)
            ->with('status', __('Module disabled successfully.'));
    }

    public function uninstall(Request $request, string $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->modules->uninstall($module);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.modules.index')
                ->withErrors(['module' => $this->actionErrorMessage($exception)]);
        }

        return redirect()
            ->route('admin.modules.index')
            ->with('status', __('Module uninstalled successfully.'));
    }

    public function updateConfig(UpdateModuleConfigRequest $request, string $module): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->modules->updateConfig($module, $request->configPayload());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failureRedirect($module, $exception);
        }

        return redirect()
            ->route('admin.modules.show', $module)
            ->with('status', __('Module configuration saved.'));
    }

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     version: string,
     *     description: string|null,
     *     capabilities: list<string>,
     *     capability_labels: list<string>,
     *     profile: string,
     *     profile_label: string,
     *     hooks: array{events: list<string>, hooks: list<string>, filters: list<string>},
     *     path: string,
     *     installed: bool,
     *     enabled: bool,
     *     loaded: bool,
     *     installation: \Core\Modules\Models\InstalledModule|null,
     *     providers: list<string>,
     *     authors: list<string>,
     *     requires: array{corepanel?: string, php?: string},
     *     config_json: string
     * }
     */
    private function present(string $key): array
    {
        $manifest = $this->modules->get($key);

        if ($manifest === null) {
            throw ModuleNotFoundException::withKey($key);
        }

        $installation = $this->modules->installation($key);
        $config = $installation?->config;

        return [
            'key' => $manifest->key,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'description' => $manifest->description,
            'capabilities' => $manifest->capabilities,
            'capability_labels' => array_map(
                static fn (string $capability): string => ModuleCapability::labelFor($capability),
                $manifest->capabilities,
            ),
            'profile' => $manifest->profile()->value,
            'profile_label' => $manifest->profile()->label(),
            'hooks' => $manifest->hookManifest->toArray(),
            'path' => $manifest->path,
            'installed' => $installation !== null,
            'enabled' => $this->modules->isEnabled($manifest->key),
            'loaded' => $this->modules->isLoaded($manifest->key),
            'installation' => $installation,
            'providers' => $manifest->providers,
            'authors' => array_map(
                static fn ($author): string => $author->name,
                $manifest->authors,
            ),
            'requires' => $manifest->requires->toArray(),
            'config_json' => $config === null || $config === []
                ? "{\n}"
                : (json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: "{\n}"),
        ];
    }

    private function failureRedirect(string $module, Throwable $exception): RedirectResponse
    {
        return redirect()
            ->route('admin.modules.show', $module)
            ->withErrors(['module' => $this->actionErrorMessage($exception)]);
    }

    private function actionErrorMessage(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ModuleSignatureException,
            $exception instanceof ModuleBootstrapException,
            $exception instanceof ModuleNotFoundException,
            $exception instanceof \Core\Modules\Exceptions\InvalidModuleManifestException => $exception->getMessage(),
            default => __('Unable to complete this module action.'),
        };
    }

    private function ensureCanView(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->permissions->userHasPermission($user, 'modules.view')) {
            abort(403, __('You do not have permission to view modules.'));
        }
    }

    private function ensureCanManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403, __('You do not have permission to manage modules.'));
        }
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $this->permissions->userHasPermission($user, 'modules.manage');
    }
}
