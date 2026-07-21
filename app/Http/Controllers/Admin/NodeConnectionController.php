<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestNodeConnectionRequest;
use Core\Nodes\Enums\NodeLogStatus;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeAuditLogger;
use Core\Nodes\Services\NodeConnectionTestService;
use Core\Nodes\Services\NodeLogService;
use Core\Nodes\Services\NodeTelemetryService;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class NodeConnectionController extends Controller
{
    public function __construct(
        private readonly NodeConnectionTestService $connectionTests,
        private readonly NodeTelemetryService $telemetry,
        private readonly NodeAuditLogger $audit,
        private readonly NodeLogService $nodeLogs,
    ) {
    }

    public function test(TestNodeConnectionRequest $request): RedirectResponse
    {
        return $this->respond(
            redirect: back()->withInput(),
            response: $this->runTest(fn () => $this->connectionTests->testPayload($request->connectionPayload())),
            node: null,
        );
    }

    public function testNode(Node $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        return $this->respond(
            redirect: back(),
            response: $this->runTest(function () use ($node): NodeOperationResponse {
                $response = $this->connectionTests->testNode($node);

                if ($response->status->isSuccessful()) {
                    $this->telemetry->refresh($node);
                }

                return $response;
            }),
            node: $node,
        );
    }

    /**
     * @param  callable(): NodeOperationResponse  $callback
     */
    private function runTest(callable $callback): NodeOperationResponse
    {
        try {
            return $callback();
        } catch (\InvalidArgumentException $exception) {
            return NodeOperationResponse::failed($exception->getMessage());
        }
    }

    private function respond(
        RedirectResponse $redirect,
        NodeOperationResponse $response,
        ?Node $node,
    ): RedirectResponse {
        if ($node !== null) {
            $this->nodeLogs->record(
                node: $node,
                action: 'node.connection.test',
                status: $response->status->isSuccessful()
                    ? NodeLogStatus::Success
                    : NodeLogStatus::Failed,
                performedBy: auth()->id(),
                response: array_filter([
                    'message' => $response->message,
                    'payload' => $response->payload,
                ], static fn (mixed $value): bool => $value !== null && $value !== []),
            );

            $this->audit->log(
                action: NodeAuditLogger::ACTION_CONNECTION_TESTED,
                node: $node,
                after: [
                    'status' => $response->status->value,
                    'message' => $response->message,
                ],
            );
        }

        if ($response->status === ProviderOperationStatus::Success) {
            return $redirect->with(
                'connection_status',
                $response->message ?? __('Connection test succeeded.'),
            );
        }

        return $redirect->withErrors([
            'connection' => $response->message ?? __('Connection test failed.'),
        ]);
    }
}
