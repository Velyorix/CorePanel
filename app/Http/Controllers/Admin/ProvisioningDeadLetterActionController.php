<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewProvisioningDeadLetterRequest;
use Core\Provisioning\Models\ProvisioningDeadLetter;
use Core\Provisioning\Services\ProvisioningDeadLetterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ProvisioningDeadLetterActionController extends Controller
{
    public function __construct(
        private readonly ProvisioningDeadLetterService $deadLetters,
    ) {
    }

    public function requeue(
        ReviewProvisioningDeadLetterRequest $request,
        ProvisioningDeadLetter $provisioningDeadLetter,
    ): RedirectResponse {
        return $this->run(
            $provisioningDeadLetter,
            fn (): mixed => $this->deadLetters->requeue(
                $provisioningDeadLetter,
                $request->user()?->id,
                $this->notes($request),
                [
                    'rollback' => true,
                    'release_node' => (bool) $request->boolean('release_node'),
                ],
            ),
            __('Provisioning requeued successfully.'),
        );
    }

    public function resolve(
        ReviewProvisioningDeadLetterRequest $request,
        ProvisioningDeadLetter $provisioningDeadLetter,
    ): RedirectResponse {
        return $this->run(
            $provisioningDeadLetter,
            fn (): mixed => $this->deadLetters->resolve(
                $provisioningDeadLetter,
                $request->user()?->id,
                $this->notes($request),
            ),
            __('Provisioning failure marked as resolved.'),
        );
    }

    public function discard(
        ReviewProvisioningDeadLetterRequest $request,
        ProvisioningDeadLetter $provisioningDeadLetter,
    ): RedirectResponse {
        return $this->run(
            $provisioningDeadLetter,
            fn (): mixed => $this->deadLetters->discard(
                $provisioningDeadLetter,
                $request->user()?->id,
                $this->notes($request),
            ),
            __('Provisioning failure discarded.'),
        );
    }

    public function rollback(
        ReviewProvisioningDeadLetterRequest $request,
        ProvisioningDeadLetter $provisioningDeadLetter,
    ): RedirectResponse {
        return $this->run(
            $provisioningDeadLetter,
            function () use ($request, $provisioningDeadLetter): array {
                return $this->deadLetters->rollback($provisioningDeadLetter, [
                    'clear_node' => $request->has('clear_node')
                        ? $request->boolean('clear_node')
                        : true,
                ]);
            },
            __('Local provisioning state rolled back.'),
        );
    }

    /**
     * @param  callable(): mixed  $action
     */
    private function run(
        ProvisioningDeadLetter $letter,
        callable $action,
        string $successMessage,
    ): RedirectResponse {
        $letter->loadMissing('service');

        Gate::authorize('manage', $letter->service);

        try {
            $action();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.provisioning-dead-letters.show', $letter)
            ->with('status', $successMessage);
    }

    private function notes(ReviewProvisioningDeadLetterRequest $request): ?string
    {
        $notes = $request->input('notes');

        if (! is_string($notes)) {
            return null;
        }

        $notes = trim($notes);

        return $notes === '' ? null : $notes;
    }
}
