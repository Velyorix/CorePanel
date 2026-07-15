<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Identity
    |--------------------------------------------------------------------------
    |
    | Nom affiché et version du CMS CorePanel. La version suit semver et sera
    | alignée sur les releases (branche release/*).
    |
    */

    'name' => env('COREPANEL_NAME', env('APP_NAME', 'CorePanel')),

    'version' => env('COREPANEL_VERSION', '0.0.0-dev'),

    /*
    |--------------------------------------------------------------------------
    | Instance (installation CMS)
    |--------------------------------------------------------------------------
    |
    | Identifiant stable de l'installation — généré une fois à l'install wizard
    | (étape 7). Utilisé pour la validation licence CorePanel.org.
    | Ne jamais régénérer sauf réinstallation volontaire.
    |
    | Voir : internal-docs/cdc/API-COREPANEL-ORG.md §6.4
    |
    */

    'instance' => [
        'id' => env('COREPANEL_INSTANCE_ID'),
        'label' => env('COREPANEL_INSTANCE_LABEL'),
        'domain' => env('COREPANEL_INSTANCE_DOMAIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CorePanel.org (plateforme licence & marketplace)
    |--------------------------------------------------------------------------
    |
    | URL de base de l'API REST v1. Endpoint licence public :
    | POST {api_url}/licenses/validate
    |
    */

    'org' => [
        'api_url' => env('COREPANEL_ORG_API_URL', 'https://corepanel.org/api/v1'),
        'health_url' => env('COREPANEL_ORG_HEALTH_URL', 'https://corepanel.org/up'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Licence (comportement local — logique en étape 7)
    |--------------------------------------------------------------------------
    */

    'license' => [
        'grace_period_hours' => (int) env('COREPANEL_LICENSE_GRACE_HOURS', 72),
        'validation_cache_hours' => (int) env('COREPANEL_LICENSE_CACHE_HOURS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    */

    'locale' => [
        'supported' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('COREPANEL_SUPPORTED_LOCALES', 'fr,en'))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance (CMS — distinct de `php artisan down`)
    |--------------------------------------------------------------------------
    */

    'maintenance' => [
        'enabled' => (bool) env('COREPANEL_MAINTENANCE', false),
        'message' => env('COREPANEL_MAINTENANCE_MESSAGE', 'CorePanel is under maintenance. Please try again later.'),
        'except' => [
            'up',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password policy (CDC Tome 4 §3.3)
    |--------------------------------------------------------------------------
    */

    'password' => [
        'min_length' => (int) env('COREPANEL_PASSWORD_MIN_LENGTH', 12),
        'require_special_character' => (bool) env('COREPANEL_PASSWORD_REQUIRE_SPECIAL', true),
        'check_compromised' => (bool) env('COREPANEL_PASSWORD_CHECK_COMPROMISED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication (CDC Tome 4 §10)
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'login' => [
            'max_attempts' => (int) env('COREPANEL_LOGIN_MAX_ATTEMPTS', 5),
            'decay_seconds' => (int) env('COREPANEL_LOGIN_DECAY_SECONDS', 60),
        ],

        /*
        |----------------------------------------------------------------------
        | Account lockout (CDC Tome 4 §13)
        |----------------------------------------------------------------------
        |
        | Locks the user account after repeated failed login attempts.
        | Unlock paths: password reset, email verification, admin unlock.
        | Set lockout_minutes to null for a lock that persists until unlock.
        |
        */

        'lockout' => [
            'enabled' => (bool) env('COREPANEL_ACCOUNT_LOCKOUT_ENABLED', true),
            'max_attempts' => (int) env('COREPANEL_ACCOUNT_LOCKOUT_MAX_ATTEMPTS', 10),
            'lockout_minutes' => env('COREPANEL_ACCOUNT_LOCKOUT_MINUTES'),
        ],

        /*
        |----------------------------------------------------------------------
        | Authentication audit (CDC Tome 4 §11)
        |----------------------------------------------------------------------
        */

        'audit' => [
            'enabled' => (bool) env('COREPANEL_AUTH_AUDIT_ENABLED', true),
        ],

        /*
        |----------------------------------------------------------------------
        | Session tracking (CDC Tome 4 §4.2)
        |----------------------------------------------------------------------
        |
        | Laravel sessions are stored via SESSION_DRIVER (redis recommended).
        | user_sessions mirrors active sessions for multi-device management.
        |
        */

        'session' => [
            'track_activity' => (bool) env('COREPANEL_SESSION_TRACK_ACTIVITY', true),
            'activity_touch_interval_seconds' => (int) env('COREPANEL_SESSION_ACTIVITY_INTERVAL', 60),
        ],

        /*
        |----------------------------------------------------------------------
        | Registration (CDC — open or invite-only)
        |----------------------------------------------------------------------
        |
        | mode: open   — public /register
        | mode: invite — registration only via /register/invitation/{token}
        |
        */

        'registration' => [
            'mode' => env('COREPANEL_REGISTRATION_MODE', 'invite'),
            'default_role' => env('COREPANEL_REGISTRATION_DEFAULT_ROLE', 'client'),
            'invitation_ttl_hours' => (int) env('COREPANEL_REGISTRATION_INVITATION_TTL', 72),
        ],

        /*
        |----------------------------------------------------------------------
        | Password reset (CDC Tome 4 §10)
        |----------------------------------------------------------------------
        */

        'password_reset' => [
            'max_attempts' => (int) env('COREPANEL_PASSWORD_RESET_MAX_ATTEMPTS', 5),
            'decay_seconds' => (int) env('COREPANEL_PASSWORD_RESET_DECAY_SECONDS', 60),
        ],

        /*
        |----------------------------------------------------------------------
        | Email verification
        |----------------------------------------------------------------------
        */

        'email_verification' => [
            'required' => (bool) env('COREPANEL_EMAIL_VERIFICATION_REQUIRED', true),
            'expire_minutes' => (int) env('COREPANEL_EMAIL_VERIFICATION_EXPIRE', 60),
            'max_attempts' => (int) env('COREPANEL_EMAIL_VERIFICATION_MAX_ATTEMPTS', 6),
            'decay_seconds' => (int) env('COREPANEL_EMAIL_VERIFICATION_DECAY_SECONDS', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RBAC (CDC Tome 4 §8, Tome 5)
    |--------------------------------------------------------------------------
    */

    'rbac' => [
        'cache' => [
            'enabled' => (bool) env('COREPANEL_RBAC_CACHE_ENABLED', true),
            'store' => env('COREPANEL_RBAC_CACHE_STORE', 'redis'),
            'prefix' => env('COREPANEL_RBAC_CACHE_PREFIX', 'corepanel.rbac'),
            'ttl_seconds' => (int) env('COREPANEL_RBAC_CACHE_TTL', 3600),
        ],

        'permissions_registry' => [
            'cache_enabled' => (bool) env('COREPANEL_RBAC_PERMISSIONS_REGISTRY_CACHE_ENABLED', true),
            'ttl_seconds' => (int) env('COREPANEL_RBAC_PERMISSIONS_REGISTRY_TTL', 3600),
        ],

        'inheritance' => [
            'enabled' => (bool) env('COREPANEL_RBAC_INHERITANCE_ENABLED', true),
        ],

        'user_overrides' => [
            'enabled' => (bool) env('COREPANEL_RBAC_USER_OVERRIDES_ENABLED', true),
        ],
    ],

];
