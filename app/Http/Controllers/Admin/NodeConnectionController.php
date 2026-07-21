<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TestNodeConnectionRequest;
use Core\Nodes\Models\Node;
use Core\Nodes\Services\NodeConnectionTestService;
use Core\Providers\DataTransferObjects\NodeOperationResponse;
use Core\Providers\Enums\ProviderOperationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class NodeConnectionController extends Controller
{
    public function __construct(
        private readonly NodeConnectionTestService $connectionTests,
    ) {
    }

    public function test(TestNodeConnectionRequest $request): RedirectResponse
    {
        return $this->respond(
            redirect: back()->withInput(),
            response: $this->runTest(fn () => $this->connectionTests->testPayload($request->connectionPayload())),
        );
    }

    public function testNode(Node $node): RedirectResponse
    {
        Gate::authorize('update', $node);

        return $this->respond(
            redirect: back(),
            response: $this->runTest(fn () => $this->connectionTests->testNode($node)),
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

    private function respond(RedirectResponse $redirect, NodeOperationResponse $response): RedirectResponse
    {
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
