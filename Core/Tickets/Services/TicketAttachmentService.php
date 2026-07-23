<?php

namespace Core\Tickets\Services;

use Core\Tickets\Models\Ticket;
use Core\Tickets\Models\TicketMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure ticket attachment storage on a private disk.
 */
class TicketAttachmentService
{
    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{
     *     id: string,
     *     original_name: string,
     *     disk: string,
     *     path: string,
     *     mime: string,
     *     size: int,
     *     uploaded_at: string
     * }>
     */
    public function storeMany(Ticket $ticket, array $files): array
    {
        if ($files === []) {
            return [];
        }

        if (! array_is_list($files)) {
            throw new InvalidArgumentException('Ticket attachment files must be a list.');
        }

        $maxFiles = max(1, (int) config('corepanel.tickets.attachments.max_files', 5));

        if (count($files) > $maxFiles) {
            throw new InvalidArgumentException(sprintf(
                'A message may include at most %d attachment(s).',
                $maxFiles,
            ));
        }

        $stored = [];

        try {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    throw new InvalidArgumentException(sprintf(
                        'Attachment at index [%d] must be an uploaded file.',
                        $index,
                    ));
                }

                $stored[] = $this->storeOne($ticket, $file);
            }
        } catch (\Throwable $exception) {
            $this->deleteStored($stored);

            throw $exception;
        }

        return $stored;
    }

    /**
     * @return array{
     *     id: string,
     *     original_name: string,
     *     disk: string,
     *     path: string,
     *     mime: string,
     *     size: int,
     *     uploaded_at: string
     * }
     */
    public function storeOne(Ticket $ticket, UploadedFile $file): array
    {
        $this->assertValidUpload($file);

        $diskName = $this->diskName();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $id = (string) Str::uuid();
        $path = trim($this->pathPrefix(), '/').'/'.$ticket->id.'/'.$id.'.'.$extension;

        $storedPath = Storage::disk($diskName)->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        if ($storedPath === false) {
            throw new RuntimeException('Failed to store ticket attachment.');
        }

        $size = (int) ($file->getSize() ?: Storage::disk($diskName)->size($storedPath));

        return [
            'id' => $id,
            'original_name' => $this->safeOriginalName($file->getClientOriginalName(), $extension),
            'disk' => $diskName,
            'path' => $storedPath,
            'mime' => (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream'),
            'size' => $size,
            'uploaded_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Locate attachment metadata on any message of the ticket.
     *
     * @return array{
     *     id: string,
     *     original_name: string,
     *     disk: string,
     *     path: string,
     *     mime: string,
     *     size: int,
     *     uploaded_at?: string
     * }
     */
    public function find(Ticket $ticket, string $attachmentId): array
    {
        $ticket->loadMissing('messages');

        foreach ($ticket->messages as $message) {
            $attachment = $this->findOnMessage($message, $attachmentId);

            if ($attachment !== null) {
                return $attachment;
            }
        }

        throw new InvalidArgumentException("Ticket attachment [{$attachmentId}] was not found.");
    }

    public function download(Ticket $ticket, string $attachmentId): StreamedResponse
    {
        $attachment = $this->find($ticket, $attachmentId);
        $disk = Storage::disk((string) $attachment['disk']);

        if (! $disk->exists((string) $attachment['path'])) {
            throw new RuntimeException('Ticket attachment file is missing from storage.');
        }

        return $disk->download(
            (string) $attachment['path'],
            (string) $attachment['original_name'],
            [
                'Content-Type' => (string) ($attachment['mime'] ?: 'application/octet-stream'),
            ],
        );
    }

    /**
     * @param  list<array{disk?: string, path?: string}>  $attachments
     */
    public function deleteStored(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $disk = (string) ($attachment['disk'] ?? $this->diskName());
            $path = (string) ($attachment['path'] ?? '');

            if ($path === '') {
                continue;
            }

            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * @return array{
     *     id: string,
     *     original_name: string,
     *     disk: string,
     *     path: string,
     *     mime: string,
     *     size: int,
     *     uploaded_at?: string
     * }|null
     */
    private function findOnMessage(TicketMessage $message, string $attachmentId): ?array
    {
        $attachments = $message->attachments;

        if (! is_array($attachments)) {
            return null;
        }

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            if ((string) ($attachment['id'] ?? '') !== $attachmentId) {
                continue;
            }

            if (! isset($attachment['path'], $attachment['disk'], $attachment['original_name'])) {
                continue;
            }

            /** @var array{id: string, original_name: string, disk: string, path: string, mime: string, size: int, uploaded_at?: string} $normalized */
            $normalized = [
                'id' => (string) $attachment['id'],
                'original_name' => (string) $attachment['original_name'],
                'disk' => (string) $attachment['disk'],
                'path' => (string) $attachment['path'],
                'mime' => (string) ($attachment['mime'] ?? 'application/octet-stream'),
                'size' => (int) ($attachment['size'] ?? 0),
            ];

            if (isset($attachment['uploaded_at'])) {
                $normalized['uploaded_at'] = (string) $attachment['uploaded_at'];
            }

            return $normalized;
        }

        return null;
    }

    private function assertValidUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('One or more ticket attachments failed to upload.');
        }

        $maxKilobytes = max(1, (int) config('corepanel.tickets.attachments.max_kilobytes', 5120));
        $size = (int) $file->getSize();

        if ($size <= 0) {
            throw new InvalidArgumentException('Ticket attachments cannot be empty.');
        }

        if ($size > ($maxKilobytes * 1024)) {
            throw new InvalidArgumentException(sprintf(
                'Ticket attachments may not be larger than %d kilobytes.',
                $maxKilobytes,
            ));
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
        $allowedExtensions = $this->allowedExtensions();

        if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException(sprintf(
                'Attachment extension [%s] is not allowed.',
                $extension !== '' ? $extension : 'unknown',
            ));
        }

        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType() ?: ''));
        $allowedMimes = $this->allowedMimes();

        if ($mime === '' || ! in_array($mime, $allowedMimes, true)) {
            throw new InvalidArgumentException(sprintf(
                'Attachment MIME type [%s] is not allowed.',
                $mime !== '' ? $mime : 'unknown',
            ));
        }
    }

    /**
     * @return list<string>
     */
    private function allowedExtensions(): array
    {
        $configured = config('corepanel.tickets.attachments.allowed_extensions', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            $configured,
        ));
    }

    /**
     * @return list<string>
     */
    private function allowedMimes(): array
    {
        $configured = config('corepanel.tickets.attachments.allowed_mimes', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $value): string => strtolower((string) $value),
            $configured,
        ));
    }

    private function diskName(): string
    {
        return (string) config('corepanel.tickets.attachments.disk', 'local');
    }

    private function pathPrefix(): string
    {
        return (string) config('corepanel.tickets.attachments.path_prefix', 'tickets');
    }

    private function safeOriginalName(string $filename, string $fallbackExtension): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? '';

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return 'attachment.'.$fallbackExtension;
        }

        return $filename;
    }
}
