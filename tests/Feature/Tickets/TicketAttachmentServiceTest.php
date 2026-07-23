<?php

namespace Tests\Feature\Tickets;

use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;
use Core\Tickets\Services\TicketAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class TicketAttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketAttachmentService $attachments;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->attachments = app(TicketAttachmentService::class);

        config([
            'corepanel.tickets.attachments.disk' => 'local',
            'corepanel.tickets.attachments.path_prefix' => 'tickets',
            'corepanel.tickets.attachments.max_files' => 2,
            'corepanel.tickets.attachments.max_kilobytes' => 100,
            'corepanel.tickets.attachments.allowed_extensions' => ['png', 'pdf', 'txt'],
            'corepanel.tickets.attachments.allowed_mimes' => [
                'image/png',
                'application/pdf',
                'text/plain',
            ],
        ]);
    }

    public function test_ticket_attachment_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(TicketAttachmentService::class),
            app(TicketAttachmentService::class),
        );
    }

    public function test_store_many_persists_files_and_metadata_on_private_disk(): void
    {
        $ticket = Ticket::factory()->create();
        $file = UploadedFile::fake()->image('shot.png', 20, 20);

        $stored = $this->attachments->storeMany($ticket, [$file]);

        $this->assertCount(1, $stored);
        $this->assertSame('shot.png', $stored[0]['original_name']);
        $this->assertSame('local', $stored[0]['disk']);
        $this->assertSame('image/png', $stored[0]['mime']);
        $this->assertNotEmpty($stored[0]['id']);
        $this->assertStringStartsWith('tickets/'.$ticket->id.'/', $stored[0]['path']);
        Storage::disk('local')->assertExists($stored[0]['path']);
    }

    public function test_download_streams_stored_attachment(): void
    {
        $ticket = Ticket::factory()->create();
        $stored = $this->attachments->storeMany($ticket, [
            UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ]);

        TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'attachments' => $stored,
        ]);

        $response = $this->attachments->download($ticket->fresh(['messages']), $stored[0]['id']);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(
            'text/plain',
            $response->headers->get('Content-Type'),
        );
    }

    public function test_find_rejects_unknown_attachment_id(): void
    {
        $ticket = Ticket::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ticket attachment [missing] was not found.');

        $this->attachments->find($ticket, 'missing');
    }

    public function test_rejects_disallowed_extension(): void
    {
        $ticket = Ticket::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attachment extension [exe] is not allowed.');

        $this->attachments->storeMany($ticket, [
            UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
        ]);
    }

    public function test_rejects_oversized_file(): void
    {
        $ticket = Ticket::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ticket attachments may not be larger than 100 kilobytes.');

        $this->attachments->storeMany($ticket, [
            UploadedFile::fake()->create('big.pdf', 101, 'application/pdf'),
        ]);
    }

    public function test_rejects_too_many_files(): void
    {
        $ticket = Ticket::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A message may include at most 2 attachment(s).');

        $this->attachments->storeMany($ticket, [
            UploadedFile::fake()->create('a.txt', 1, 'text/plain'),
            UploadedFile::fake()->create('b.txt', 1, 'text/plain'),
            UploadedFile::fake()->create('c.txt', 1, 'text/plain'),
        ]);
    }

    public function test_delete_stored_removes_files_from_disk(): void
    {
        $ticket = Ticket::factory()->create();
        $stored = $this->attachments->storeMany($ticket, [
            UploadedFile::fake()->create('gone.txt', 5, 'text/plain'),
        ]);

        Storage::disk('local')->assertExists($stored[0]['path']);

        $this->attachments->deleteStored($stored);

        Storage::disk('local')->assertMissing($stored[0]['path']);
    }
}
