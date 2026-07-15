<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ComponentShowcaseController extends Controller
{
    public function __invoke(): View
    {
        abort_unless($this->showcaseEnabled(), Response::HTTP_NOT_FOUND);

        $rows = collect([
            ['name' => 'Acme Corp', 'status' => 'active'],
            ['name' => 'Nova Cloud', 'status' => 'pending'],
            ['name' => 'Orbit Labs', 'status' => 'active'],
            ['name' => 'Pioneer Host', 'status' => 'pending'],
            ['name' => 'Zenith Nodes', 'status' => 'active'],
        ]);

        $paginator = new LengthAwarePaginator(
            items: $rows->forPage(1, 3)->values(),
            total: $rows->count(),
            perPage: 3,
            currentPage: 1,
            options: [
                'path' => route('dev.components'),
                'query' => request()->query(),
            ],
        );

        return view('dev.components', [
            'paginator' => $paginator,
        ]);
    }

    private function showcaseEnabled(): bool
    {
        $configured = config('corepanel.ui.showcase.enabled');

        if ($configured !== null) {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return app()->environment('local');
    }
}
