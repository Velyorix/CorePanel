<?php

namespace Core\Tickets\DataTransferObjects;

use Core\Tickets\Enums\TicketPriority;
use Illuminate\Http\UploadedFile;

readonly class TicketData
{
    /**
     * @param  list<UploadedFile>|null  $files
     */
    public function __construct(
        public string $subject,
        public string $message,
        public ?int $categoryId = null,
        public TicketPriority $priority = TicketPriority::Normal,
        public ?array $files = null,
    ) {
    }

    /**
     * @param  array{
     *     subject?: string|null,
     *     message?: string|null,
     *     category_id?: int|null,
     *     priority?: string|null,
     *     files?: list<UploadedFile>|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $priority = TicketPriority::tryFrom((string) ($data['priority'] ?? TicketPriority::Normal->value))
            ?? TicketPriority::Normal;

        return new self(
            subject: trim((string) ($data['subject'] ?? '')),
            message: trim((string) ($data['message'] ?? '')),
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            priority: $priority,
            files: $data['files'] ?? null,
        );
    }
}
