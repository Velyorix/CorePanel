<?php

namespace Core\Auth\Rules;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /**
     * Build the default CorePanel password validation rule.
     *
     * @return list<Password|string>
     */
    public static function defaults(): array
    {
        $rule = Password::min((int) config('corepanel.password.min_length', 12))
            ->letters()
            ->mixedCase()
            ->numbers();

        if (config('corepanel.password.require_special_character', true)) {
            $rule->symbols();
        }

        if (config('corepanel.password.check_compromised', true)) {
            $rule->uncompromised();
        }

        return [$rule];
    }
}
