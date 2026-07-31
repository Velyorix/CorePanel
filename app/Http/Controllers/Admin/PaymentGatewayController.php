<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentGatewayConfigRequest;
use Core\Billing\Exceptions\UnknownPaymentGatewayException;
use Core\Billing\Services\GatewayManager;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly PermissionService $permissions,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureCanView($request);

        $this->gateways->sync();

        $gateways = collect($this->gateways->orderedKeys())
            ->map(fn (string $key): array => $this->present($key))
            ->values();

        return view('admin.gateways.index', [
            'gateways' => $gateways,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function show(Request $request, string $gateway): View
    {
        $this->ensureCanView($request);

        return view('admin.gateways.show', [
            'gateway' => $this->present($gateway),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function enable(Request $request, string $gateway): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->gateways->enable($gateway);
        } catch (Throwable $exception) {
            return $this->failureRedirect($gateway, $exception);
        }

        return redirect()
            ->route('admin.gateways.show', $gateway)
            ->with('status', __('Payment gateway enabled.'));
    }

    public function disable(Request $request, string $gateway): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->gateways->disable($gateway);
        } catch (Throwable $exception) {
            return $this->failureRedirect($gateway, $exception);
        }

        return redirect()
            ->route('admin.gateways.show', $gateway)
            ->with('status', __('Payment gateway disabled.'));
    }

    public function updateConfig(UpdatePaymentGatewayConfigRequest $request, string $gateway): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->gateways->configure($gateway, $request->configPayload(), merge: false);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failureRedirect($gateway, $exception);
        }

        return redirect()
            ->route('admin.gateways.show', $gateway)
            ->with('status', __('Payment gateway configuration saved.'));
    }

    public function moveUp(Request $request, string $gateway): RedirectResponse
    {
        return $this->move($request, $gateway, 'up');
    }

    public function moveDown(Request $request, string $gateway): RedirectResponse
    {
        return $this->move($request, $gateway, 'down');
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     enabled: bool,
     *     sort_order: int,
     *     config_json: string,
     *     registered: bool
     * }
     */
    private function present(string $key): array
    {
        if (! $this->gateways->has($key)) {
            throw UnknownPaymentGatewayException::forKey($key);
        }

        $implementation = $this->gateways->resolve($key, onlyEnabled: false);
        $record = $this->gateways->record($key);
        $config = $record?->config;

        return [
            'key' => $implementation->key(),
            'label' => $implementation->label(),
            'enabled' => $this->gateways->isEnabled($key),
            'sort_order' => (int) ($record?->sort_order ?? 0),
            'config_json' => $config === null || $config === []
                ? "{\n}"
                : (json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: "{\n}"),
            'registered' => true,
        ];
    }

    private function move(Request $request, string $gateway, string $direction): RedirectResponse
    {
        $this->ensureCanManage($request);

        try {
            $this->gateways->move($gateway, $direction);
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.gateways.index')
                ->withErrors(['gateway' => $this->actionErrorMessage($exception)]);
        }

        return redirect()
            ->route('admin.gateways.index')
            ->with('status', __('Payment gateway order updated.'));
    }

    private function failureRedirect(string $gateway, Throwable $exception): RedirectResponse
    {
        return redirect()
            ->route('admin.gateways.show', $gateway)
            ->withErrors(['gateway' => $this->actionErrorMessage($exception)]);
    }

    private function actionErrorMessage(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof UnknownPaymentGatewayException => $exception->getMessage(),
            default => __('Unable to complete this gateway action.'),
        };
    }

    private function ensureCanView(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->permissions->userHasPermission($user, 'settings.view')) {
            abort(403, __('You do not have permission to view payment gateways.'));
        }
    }

    private function ensureCanManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403, __('You do not have permission to manage payment gateways.'));
        }
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $this->permissions->userHasPermission($user, 'settings.manage');
    }
}
