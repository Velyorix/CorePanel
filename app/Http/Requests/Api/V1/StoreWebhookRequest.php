<?php

namespace App\Http\Requests\Api\V1;

use Core\Webhooks\Enums\WebhookEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048', 'regex:/^https:\\/\\//i'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in([...WebhookEvent::values(), '*'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, url: string, events: list<string>, is_active?: bool}
     */
    public function webhookData(): array
    {
        $validated = $this->validated();

        return [
            'name' => (string) $validated['name'],
            'url' => (string) $validated['url'],
            'events' => array_values($validated['events']),
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : true,
        ];
    }
}
