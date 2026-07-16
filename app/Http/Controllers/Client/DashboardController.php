<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('client.dashboard', [
            'kpis' => $this->kpiPlaceholders(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, hint: string, value: string, section: string}>
     */
    private function kpiPlaceholders(): array
    {
        return [
            [
                'key' => 'services_active',
                'label' => __('Active services'),
                'hint' => __('Currently running services'),
                'value' => '—',
                'section' => 'services',
            ],
            [
                'key' => 'services_suspended',
                'label' => __('Suspended services'),
                'hint' => __('Services awaiting renewal or action'),
                'value' => '—',
                'section' => 'services',
            ],
            [
                'key' => 'services_status',
                'label' => __('Overall service status'),
                'hint' => __('Health summary across your services'),
                'value' => '—',
                'section' => 'services',
            ],
            [
                'key' => 'invoices_unpaid',
                'label' => __('Unpaid invoices'),
                'hint' => __('Outstanding billing items'),
                'value' => '—',
                'section' => 'billing',
            ],
            [
                'key' => 'credits_available',
                'label' => __('Available credits'),
                'hint' => __('Account credit balance'),
                'value' => '—',
                'section' => 'billing',
            ],
            [
                'key' => 'tickets_open',
                'label' => __('Open tickets'),
                'hint' => __('Support requests awaiting a response'),
                'value' => '—',
                'section' => 'support',
            ],
        ];
    }
}
