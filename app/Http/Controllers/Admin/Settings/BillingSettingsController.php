<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Requests\Admin\UpdateBillingSettingsRequest;
use Core\Billing\Services\BillingSettings;
use Core\Permissions\Services\PermissionService;
use Core\Settings\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingSettingsController extends SettingsController
{
    public const DEFAULT_CURRENCY = 'billing.default_currency';

    public const TAX_PREVIEW_RATE = 'billing.tax_preview_rate';

    public const RENEWAL_ENABLED = 'billing.renewal_enabled';

    public function __construct(
        PermissionService $permissionService,
        private readonly SettingsService $settings,
        private readonly BillingSettings $billing,
    ) {
        parent::__construct($permissionService);
    }

    public function edit(Request $request): View
    {
        $this->ensureCanView($request);

        return view('admin.settings.billing', [
            'values' => [
                'invoice_prefix' => $this->billing->invoicePrefix(),
                'quote_prefix' => $this->billing->quotePrefix(),
                'credit_note_prefix' => $this->billing->creditNotePrefix(),
                'default_currency' => $this->settings->getString(self::DEFAULT_CURRENCY, 'EUR'),
                'tax_preview_rate' => $this->settings->getString(
                    self::TAX_PREVIEW_RATE,
                    (string) config('corepanel.billing.tax_preview_rate', '0'),
                ),
                'renewal_enabled' => $this->settings->getBool(
                    self::RENEWAL_ENABLED,
                    (bool) config('corepanel.billing.renewal.enabled', true),
                ),
            ],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(UpdateBillingSettingsRequest $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validated();

        $this->billing->setInvoicePrefix($validated['invoice_prefix']);
        $this->billing->setQuotePrefix($validated['quote_prefix']);
        $this->billing->setCreditNotePrefix($validated['credit_note_prefix']);

        $this->settings->set(self::DEFAULT_CURRENCY, strtoupper($validated['default_currency']), 'string', true);
        $this->settings->set(self::TAX_PREVIEW_RATE, $validated['tax_preview_rate'], 'string', true);
        $this->settings->set(
            self::RENEWAL_ENABLED,
            $request->boolean('renewal_enabled'),
            'boolean',
            true,
        );

        return redirect()
            ->route('admin.settings.billing')
            ->with('status', __('Billing settings saved successfully.'));
    }
}
