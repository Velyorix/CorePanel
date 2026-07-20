<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexProvisioningDeadLetterRequest;
use Core\Provisioning\Enums\ProvisioningDeadLetterStatus;
use Core\Provisioning\Models\ProvisioningDeadLetter;
use Core\Provisioning\Services\ProvisioningDeadLetterService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProvisioningDeadLetterController extends Controller
{
    public function __construct(
        private readonly ProvisioningDeadLetterService $deadLetters,
    ) {
    }

    public function index(IndexProvisioningDeadLetterRequest $request): View
    {
        $filters = $request->filters();

        if ($filters['status'] === null && ! $request->has('status')) {
            $filters['status'] = ProvisioningDeadLetterStatus::PendingReview;
        }

        return view('admin.provisioning-dead-letters.index', [
            'letters' => $this->deadLetters->paginateForAdmin($filters),
            'filters' => $filters,
            'statuses' => ProvisioningDeadLetterStatus::cases(),
        ]);
    }

    public function show(ProvisioningDeadLetter $provisioningDeadLetter): View
    {
        $provisioningDeadLetter->load(['service.client', 'service.product', 'service.node', 'resolver']);

        Gate::authorize('view', $provisioningDeadLetter->service);

        return view('admin.provisioning-dead-letters.show', [
            'letter' => $provisioningDeadLetter,
        ]);
    }
}
