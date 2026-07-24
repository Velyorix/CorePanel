<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReplyTicketRequest;
use App\Http\Requests\Api\V1\StoreTicketRequest;
use Core\API\Http\Presenters\V1\ApiResourcePresenter;
use Core\API\Services\ApiClientAccessService;
use Core\API\Support\ApiPaginationMeta;
use Core\API\Support\ApiResponse;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Tickets\DataTransferObjects\TicketData;
use Core\Tickets\Models\Ticket;
use Core\Tickets\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TicketController extends Controller
{
    public function __construct(
        private readonly ApiClientAccessService $access,
        private readonly TicketService $tickets,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ids = $this->access->accessibleClientIds($user);

        $paginator = Ticket::query()
            ->whereIn('client_id', $ids === [] ? [0] : $ids)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success(
            collect($paginator->items())
                ->map(static fn (Ticket $ticket): array => ApiResourcePresenter::ticket($ticket))
                ->values()
                ->all(),
            $request,
            ApiPaginationMeta::fromPaginator($paginator),
        );
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->access->assertCanAccessOwnedResource($user, $ticket);

        $ticket->load(['messages.author', 'category']);

        return ApiResponse::success(ApiResourcePresenter::ticket($ticket), $request);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $client = Client::query()->findOrFail((int) $request->validated('client_id'));
        $this->access->assertCanAccessClient($user, $client);

        try {
            $ticket = $this->tickets->create(
                $client,
                $user,
                TicketData::fromArray($request->validated()),
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error('ticket_create_failed', $exception->getMessage(), 422);
        }

        return ApiResponse::success(ApiResourcePresenter::ticket($ticket), $request, status: 201);
    }

    public function reply(ReplyTicketRequest $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->access->assertCanAccessOwnedResource($user, $ticket);

        try {
            $message = $this->tickets->reply(
                $ticket,
                $user,
                (string) $request->validated('message'),
            );
        } catch (RuntimeException $exception) {
            return ApiResponse::error('ticket_reply_failed', $exception->getMessage(), 422);
        }

        $message->loadMissing('author');

        return ApiResponse::success(ApiResourcePresenter::ticketMessage($message), $request, status: 201);
    }
}
