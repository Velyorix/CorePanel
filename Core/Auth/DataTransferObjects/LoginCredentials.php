<?php

namespace Core\Auth\DataTransferObjects;

readonly class LoginCredentials
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
    ) {
    }

    /**
     * @param  array{email: string, password: string, remember?: bool}  $validated
     */
    public static function fromArray(array $validated): self
    {
        return new self(
            email: $validated['email'],
            password: $validated['password'],
            remember: (bool) ($validated['remember'] ?? false),
        );
    }
}
