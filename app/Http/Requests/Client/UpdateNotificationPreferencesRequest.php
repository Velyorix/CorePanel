<?php

namespace App\Http\Requests\Client;

use Core\Notifications\Services\NotificationPreferenceService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.account.manage') ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'channels' => ['nullable', 'array'],
            'channels.*' => ['sometimes', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['nullable', 'array'],
            'categories.*.*' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{
     *     channels: array<string, bool>,
     *     categories: array<string, array<string, bool>>
     * }
     */
    public function normalized(): array
    {
        $channels = [];
        foreach (NotificationPreferenceService::CHANNELS as $channel) {
            $channels[$channel] = $this->boolean("channels.{$channel}");
        }

        $categories = [];
        foreach (NotificationPreferenceService::CATEGORIES as $category) {
            $categories[$category] = [];
            foreach (NotificationPreferenceService::CHANNELS as $channel) {
                $categories[$category][$channel] = $this->boolean("categories.{$category}.{$channel}");
            }
        }

        return [
            'channels' => $channels,
            'categories' => $categories,
        ];
    }
}
