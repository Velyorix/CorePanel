<?php

namespace Core\Clients\Services;

use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientNote;
use InvalidArgumentException;

class ClientNoteService
{
    public function __construct(
        private readonly ClientAuditLogger $clientAuditLogger,
    ) {
    }

    public function create(Client $client, User $author, string $body): ClientNote
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('The note body cannot be empty.');
        }

        $note = ClientNote::query()->create([
            'client_id' => $client->id,
            'user_id' => $author->id,
            'body' => $body,
        ]);

        $this->clientAuditLogger->log(
            ClientAuditLogger::ACTION_NOTE_CREATED,
            $client,
            after: [
                'note_id' => $note->id,
                'author_id' => $author->id,
                'body_preview' => mb_substr($body, 0, 120),
            ],
            actorId: $author->id,
        );

        return $note->load('author');
    }

    public function delete(Client $client, ClientNote $note, User $actor): void
    {
        if ($note->client_id !== $client->id) {
            throw new InvalidArgumentException('Note does not belong to this client.');
        }

        $before = [
            'note_id' => $note->id,
            'author_id' => $note->user_id,
            'body_preview' => mb_substr($note->body, 0, 120),
        ];

        $note->delete();

        $this->clientAuditLogger->log(
            ClientAuditLogger::ACTION_NOTE_DELETED,
            $client,
            before: $before,
            actorId: $actor->id,
        );
    }
}
