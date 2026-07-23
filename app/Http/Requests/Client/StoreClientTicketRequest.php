<?php

namespace App\Http\Requests\Client;

use Core\Auth\Models\User;
use Core\Billing\Models\Invoice;
use Core\Clients\Models\Client;
use Core\Orders\Models\Order;
use Core\Services\Models\Service;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Models\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreClientTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.tickets.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['category_id', 'service_id', 'order_id', 'invoice_id'] as $field) {
            if ($this->input($field) === '' || $this->input($field) === null) {
                $this->merge([$field => null]);
            }
        }

        if ($this->input('priority') === '') {
            $this->merge(['priority' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFiles = max(1, (int) config('corepanel.tickets.attachments.max_files', 5));
        $maxKilobytes = max(1, (int) config('corepanel.tickets.attachments.max_kilobytes', 5120));
        $clientId = $this->resolveClient()?->id;

        return [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists((new TicketCategory)->getTable(), 'id'),
            ],
            'priority' => ['nullable', 'string', Rule::in(TicketPriority::values())],
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists((new Service)->getTable(), 'id')->where(
                    fn ($query) => $clientId === null
                        ? $query->whereRaw('1 = 0')
                        : $query->where('client_id', $clientId),
                ),
            ],
            'order_id' => [
                'nullable',
                'integer',
                Rule::exists((new Order)->getTable(), 'id')->where(
                    fn ($query) => $clientId === null
                        ? $query->whereRaw('1 = 0')
                        : $query->where('client_id', $clientId),
                ),
            ],
            'invoice_id' => [
                'nullable',
                'integer',
                Rule::exists((new Invoice)->getTable(), 'id')->where(
                    fn ($query) => $clientId === null
                        ? $query->whereRaw('1 = 0')
                        : $query->where('client_id', $clientId),
                ),
            ],
            'files' => ['nullable', 'array', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:'.$maxKilobytes],
        ];
    }

    /**
     * @return array{
     *     subject: string,
     *     message: string,
     *     category_id: int|null,
     *     priority: string|null,
     *     service_id: int|null,
     *     order_id: int|null,
     *     invoice_id: int|null,
     *     files: list<UploadedFile>|null
     * }
     */
    public function payload(): array
    {
        $validated = $this->validated();

        /** @var list<UploadedFile>|null $files */
        $files = $this->file('files');

        return [
            'subject' => trim((string) $validated['subject']),
            'message' => trim((string) $validated['message']),
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'priority' => isset($validated['priority']) ? (string) $validated['priority'] : null,
            'service_id' => isset($validated['service_id']) ? (int) $validated['service_id'] : null,
            'order_id' => isset($validated['order_id']) ? (int) $validated['order_id'] : null,
            'invoice_id' => isset($validated['invoice_id']) ? (int) $validated['invoice_id'] : null,
            'files' => ($files === null || $files === []) ? null : array_values($files),
        ];
    }

    private function resolveClient(): ?Client
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->clients()->orderBy('clients.id')->first()
            ?? $user->ownedClients()->orderBy('id')->first();
    }
}
