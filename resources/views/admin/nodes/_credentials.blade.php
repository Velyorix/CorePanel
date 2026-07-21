@php
    use Core\Nodes\Enums\NodeCredentialField;

    $credentialValues = old('credentials', []);
@endphp

<div class="sm:col-span-2 border-t border-border pt-4">
    <h3 class="text-body font-medium text-foreground">{{ __('Credentials') }}</h3>
    <p class="mt-1 text-small text-muted-foreground">
        {{ __('Secrets are encrypted at rest and never shown again after saving.') }}
    </p>
</div>

@foreach (NodeCredentialField::cases() as $field)
    @if ($field === NodeCredentialField::SshPrivateKey)
        <div class="sm:col-span-2">
            <label for="credentials_{{ $field->value }}" class="mb-1.5 block text-body-sm font-medium text-foreground">
                {{ $field->label() }}
            </label>
            <textarea
                id="credentials_{{ $field->value }}"
                name="credentials[{{ $field->value }}]"
                rows="4"
                autocomplete="off"
                class="block w-full rounded-md border border-border bg-surface px-3 py-2 font-mono text-body-sm text-foreground shadow-sm transition placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                placeholder="{{ __('Paste private key…') }}"
            >{{ $credentialValues[$field->value] ?? '' }}</textarea>
            @error('credentials.'.$field->value)
                <p class="mt-1.5 text-small text-danger-700 dark:text-danger-400" role="alert">{{ $message }}</p>
            @enderror
        </div>
    @else
        <x-ui.input
            name="credentials[{{ $field->value }}]"
            :type="$field->isSecret() ? 'password' : 'text'"
            :label="$field->label()"
            :value="$credentialValues[$field->value] ?? ''"
            autocomplete="off"
        />
    @endif
@endforeach
