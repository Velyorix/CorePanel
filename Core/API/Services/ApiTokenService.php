<?php

namespace Core\API\Services;

use Carbon\CarbonInterface;
use Core\API\Models\ApiToken;
use Core\Auth\Models\User;
use Illuminate\Support\Str;

class ApiTokenService
{
    public function __construct(
        private readonly ApiScopeRegistry $scopes,
    ) {
    }

    /**
     * @param  list<string>|null  $permissions
     * @return array{plain_text: string, token: ApiToken}
     */
    public function issue(
        User $user,
        string $name,
        ?array $permissions = null,
        ?CarbonInterface $expiresAt = null,
    ): array {
        if ($permissions !== null) {
            $permissions = $this->scopes->assertKnown($permissions);
        }

        $plainText = $this->generatePlainText();

        $token = ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'token_prefix' => $this->prefixOf($plainText),
            'token_hash' => $this->hash($plainText),
            'permissions' => $permissions,
            'expires_at' => $expiresAt,
        ]);

        return [
            'plain_text' => $plainText,
            'token' => $token,
        ];
    }

    public function findValidByPlainText(string $plainText): ?ApiToken
    {
        $prefix = (string) config('corepanel.api.token_prefix', 'cpat_');

        if ($plainText === '' || ! str_starts_with($plainText, $prefix)) {
            return null;
        }

        $token = ApiToken::query()
            ->where('token_prefix', $this->prefixOf($plainText))
            ->where('token_hash', $this->hash($plainText))
            ->with('user')
            ->first();

        if ($token === null || ! $token->isValid()) {
            return null;
        }

        $user = $token->user;

        if (! $user instanceof User || $user->status !== 'active') {
            return null;
        }

        return $token;
    }

    public function touchLastUsed(ApiToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }

    public function revoke(ApiToken $token): void
    {
        if ($token->revoked_at !== null) {
            return;
        }

        $token->forceFill(['revoked_at' => now()])->save();
    }

    public function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }

    public function generatePlainText(): string
    {
        $prefix = (string) config('corepanel.api.token_prefix', 'cpat_');
        $length = max(32, (int) config('corepanel.api.token_entropy_length', 40));

        return $prefix.Str::random($length);
    }

    public function prefixOf(string $plainText): string
    {
        return substr($plainText, 0, 12);
    }

    /**
     * @param  list<string>  $requiredScopes
     */
    public function tokenHasAllScopes(ApiToken $token, array $requiredScopes): bool
    {
        return app(ApiTokenScopeChecker::class)->allows($token, $requiredScopes);
    }
}
