<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'kpis' => $this->kpiPlaceholders(),
            'charts' => $this->chartPlaceholders(),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, hint: string, value: string}>
     */
    private function kpiPlaceholders(): array
    {
        return [
            [
                'key' => 'revenue',
                'label' => __('Total revenue'),
                'hint' => __('Monthly / yearly'),
                'value' => '—',
            ],
            [
                'key' => 'clients',
                'label' => __('Active clients'),
                'hint' => __('Currently active accounts'),
                'value' => '—',
            ],
            [
                'key' => 'services',
                'label' => __('Active services'),
                'hint' => __('Provisioned and running'),
                'value' => '—',
            ],
            [
                'key' => 'renewal',
                'label' => __('Renewal rate'),
                'hint' => __('Rolling period'),
                'value' => '—',
            ],
            [
                'key' => 'tickets',
                'label' => __('Open tickets'),
                'hint' => __('Awaiting staff action'),
                'value' => '—',
            ],
            [
                'key' => 'system',
                'label' => __('System status'),
                'hint' => __('Overall platform health'),
                'value' => '—',
            ],
            [
                'key' => 'nodes',
                'label' => __('Node load'),
                'hint' => __('Infrastructure utilization'),
                'value' => '—',
            ],
            [
                'key' => 'alerts',
                'label' => __('Critical alerts'),
                'hint' => __('Requires immediate attention'),
                'value' => '—',
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    private function chartPlaceholders(): array
    {
        return [
            [
                'key' => 'revenue_daily',
                'label' => __('Revenue by day'),
                'description' => __('Chart placeholder — wired in a later étape.'),
            ],
            [
                'key' => 'services_created',
                'label' => __('Service creation'),
                'description' => __('Chart placeholder — wired in a later étape.'),
            ],
            [
                'key' => 'support_tickets',
                'label' => __('Support tickets'),
                'description' => __('Chart placeholder — wired in a later étape.'),
            ],
            [
                'key' => 'infrastructure',
                'label' => __('Infrastructure usage'),
                'description' => __('Chart placeholder — wired in a later étape.'),
            ],
        ];
    }
}
