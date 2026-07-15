<?php

namespace Core\Auth\Models;

use Core\Auth\Notifications\ResetPasswordNotification;
use Core\Auth\Notifications\VerifyEmailNotification;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Support\Models\AuditLog;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'status',
    'email_verified_at',
    'two_factor_enabled',
    'two_factor_secret',
    'last_login_at',
    'failed_login_attempts',
    'locked_at',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable implements CanResetPasswordContract, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use CanResetPassword, HasFactory, MustVerifyEmail, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<UserSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Direct permission overrides (grant / deny) applied on top of role permissions.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot('effect');
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function ownedClients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<ClientUser, $this>
     */
    public function clientMemberships(): HasMany
    {
        return $this->hasMany(ClientUser::class);
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_users')
            ->withPivot(['role', 'permissions', 'created_at']);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
