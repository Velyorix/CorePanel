<?php

namespace App\Http\Requests\Admin;

use Core\Tickets\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');

        return $this->user()?->can('reply', $ticket) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFiles = max(1, (int) config('corepanel.tickets.attachments.max_files', 5));
        $maxKilobytes = max(1, (int) config('corepanel.tickets.attachments.max_kilobytes', 5120));

        return [
            'message' => ['required', 'string', 'max:10000'],
            'files' => ['nullable', 'array', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:'.$maxKilobytes],
        ];
    }

    public function message(): string
    {
        return trim((string) $this->validated('message'));
    }

    /**
     * @return list<UploadedFile>|null
     */
    public function filesList(): ?array
    {
        /** @var list<UploadedFile>|null $files */
        $files = $this->file('files');

        if ($files === null || $files === []) {
            return null;
        }

        return array_values($files);
    }
}
