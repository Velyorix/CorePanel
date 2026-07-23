<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreClientTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.tickets.reply') ?? false;
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
