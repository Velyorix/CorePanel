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
    |  Utilisé pour la validation licence CorePanel.org.
    | Ne jamais régénérer sauf réinstallation volontaire.
    |
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
        'timeout_seconds' => (int) env('COREPANEL_ORG_TIMEOUT_SECONDS', 10),
        /*
        | SSL verification for outbound calls to CorePanel.org.
        | Defaults to false in local (common Windows CA bundle issue) and true elsewhere.
        | Prefer setting COREPANEL_ORG_CA_BUNDLE to a cacert.pem path in production.
        */
        'verify_ssl' => filter_var(
            env('COREPANEL_ORG_VERIFY_SSL', env('APP_ENV') !== 'local'),
            FILTER_VALIDATE_BOOL,
        ),
        'ca_bundle' => env('COREPANEL_ORG_CA_BUNDLE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Licence (comportement local)
    |--------------------------------------------------------------------------
    */

    'license' => [
        'grace_period_hours' => (int) env('COREPANEL_LICENSE_GRACE_HOURS', 72),
        'validation_cache_hours' => (int) env('COREPANEL_LICENSE_CACHE_HOURS', 12),

        /*
        | Exponential backoff after HTTP 429 (seconds).
        | Overridden by Retry-After / error.details.retry_after when present.
        */
        'backoff_seconds' => array_values(array_filter(array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', (string) env('COREPANEL_LICENSE_BACKOFF_SECONDS', '60,120,300')),
        ), static fn (int $value): bool => $value > 0)) ?: [60, 120, 300],

        /*
        | Routes accessible without a valid license (install wizard, auth, health).
        */
        'except' => [
            'install/license',
            'install/license/*',
            'admin/license',
            'admin/license/*',
            'login',
            'logout',
            'forgot-password',
            'reset-password/*',
            'register',
            'register/*',
            'email/verify',
            'email/verify/*',
            'email/verification-notification',
            'up',
        ],
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
    | Password policy
    |--------------------------------------------------------------------------
    */

    'password' => [
        'min_length' => (int) env('COREPANEL_PASSWORD_MIN_LENGTH', 12),
        'require_special_character' => (bool) env('COREPANEL_PASSWORD_REQUIRE_SPECIAL', true),
        'check_compromised' => (bool) env('COREPANEL_PASSWORD_CHECK_COMPROMISED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    'auth' => [
        'login' => [
            'max_attempts' => (int) env('COREPANEL_LOGIN_MAX_ATTEMPTS', 5),
            'decay_seconds' => (int) env('COREPANEL_LOGIN_DECAY_SECONDS', 60),
        ],

        /*
        |----------------------------------------------------------------------
        | Account lockout
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
        | Authentication audit
        |----------------------------------------------------------------------
        */

        'audit' => [
            'enabled' => (bool) env('COREPANEL_AUTH_AUDIT_ENABLED', true),
        ],

        /*
        |----------------------------------------------------------------------
        | Session tracking
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
        | Registration
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
        | Client invitations
        |----------------------------------------------------------------------
        */
        'client_invitations' => [
            'invitation_ttl_hours' => (int) env('COREPANEL_CLIENT_INVITATION_TTL', 72),
        ],

        /*
        |----------------------------------------------------------------------
        | Password reset
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
    | Clients
    |--------------------------------------------------------------------------
    */

    'clients' => [
        'audit' => [
            'enabled' => (bool) env('COREPANEL_CLIENTS_AUDIT_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    */

    'billing' => [
        /*
        | Seller country and EU member list used by TaxCalculationService.
        */
        'seller_country' => env('COREPANEL_BILLING_SELLER_COUNTRY', 'FR'),
        'eu_countries' => [
            'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
            'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
            'PT', 'RO', 'SE', 'SI', 'SK',
        ],

        /*
        | Seller letterhead for invoice / quote PDFs.
        */
        'seller' => [
            'name' => env('COREPANEL_BILLING_SELLER_NAME', 'CorePanel'),
            'address' => env('COREPANEL_BILLING_SELLER_ADDRESS'),
            'city' => env('COREPANEL_BILLING_SELLER_CITY'),
            'postal_code' => env('COREPANEL_BILLING_SELLER_POSTAL_CODE'),
            'country' => env('COREPANEL_BILLING_SELLER_COUNTRY', 'FR'),
            'vat_number' => env('COREPANEL_BILLING_SELLER_VAT'),
            'email' => env('COREPANEL_BILLING_SELLER_EMAIL'),
            'phone' => env('COREPANEL_BILLING_SELLER_PHONE'),
            'logo_path' => env('COREPANEL_BILLING_SELLER_LOGO_PATH'),
            'footer' => env('COREPANEL_BILLING_SELLER_FOOTER'),
        ],

        /*
        | Billing audit trail (invoice/payment/quote/credit lifecycle events).
        */
        'audit' => [
            'enabled' => (bool) env('COREPANEL_BILLING_AUDIT_ENABLED', true),
        ],

        /*
        | Fallback tax rate when billing country is unknown (catalog/cart preview).
        */
        'tax_preview_rate' => (float) env('COREPANEL_BILLING_TAX_PREVIEW_RATE', 0),
        'tax_preview_label' => env('COREPANEL_BILLING_TAX_PREVIEW_LABEL'),

        /*
        | Invoice numbering
        | Runtime prefix override: settings key billing.invoice.prefix via BillingSettings.
        | Sequence counter lives in billing_sequences (locked under concurrency).
        */
        'invoice_numbering' => [
            'prefix' => env('COREPANEL_BILLING_INVOICE_PREFIX', 'INV'),
            'padding' => (int) env('COREPANEL_BILLING_INVOICE_PADDING', 6),
            'include_year' => (bool) env('COREPANEL_BILLING_INVOICE_INCLUDE_YEAR', true),
            'reset_yearly' => (bool) env('COREPANEL_BILLING_INVOICE_RESET_YEARLY', true),
            'separator' => env('COREPANEL_BILLING_INVOICE_SEPARATOR', '-'),
        ],

        /*
        | Quote numbering (assigned on send).
        | Runtime prefix override: settings key billing.quote.prefix via BillingSettings.
        */
        'quote_numbering' => [
            'prefix' => env('COREPANEL_BILLING_QUOTE_PREFIX', 'QUO'),
            'padding' => (int) env('COREPANEL_BILLING_QUOTE_PADDING', 6),
            'include_year' => (bool) env('COREPANEL_BILLING_QUOTE_INCLUDE_YEAR', true),
            'reset_yearly' => (bool) env('COREPANEL_BILLING_QUOTE_RESET_YEARLY', true),
            'separator' => env('COREPANEL_BILLING_QUOTE_SEPARATOR', '-'),
        ],

        /*
        | Credit note numbering (assigned on issue).
        | Runtime prefix override: settings key billing.credit_note.prefix via BillingSettings.
        */
        'credit_note_numbering' => [
            'prefix' => env('COREPANEL_BILLING_CREDIT_NOTE_PREFIX', 'CN'),
            'padding' => (int) env('COREPANEL_BILLING_CREDIT_NOTE_PADDING', 6),
            'include_year' => (bool) env('COREPANEL_BILLING_CREDIT_NOTE_INCLUDE_YEAR', true),
            'reset_yearly' => (bool) env('COREPANEL_BILLING_CREDIT_NOTE_RESET_YEARLY', true),
            'separator' => env('COREPANEL_BILLING_CREDIT_NOTE_SEPARATOR', '-'),
        ],

        'quote_valid_days' => (int) env('COREPANEL_BILLING_QUOTE_VALID_DAYS', 30),
        'invoice_due_days' => (int) env('COREPANEL_BILLING_INVOICE_DUE_DAYS', 14),

        /*
        | Recurring renewal invoice generation (GenerateRenewalInvoices job).
        */
        'renewal' => [
            'enabled' => (bool) env('COREPANEL_BILLING_RENEWAL_ENABLED', true),
            'invoice_days_before' => (int) env('COREPANEL_BILLING_RENEWAL_DAYS_BEFORE', 7),
            'schedule' => env('COREPANEL_BILLING_RENEWAL_SCHEDULE', 'daily'),
        ],

        /*
        | Staged invoice payment reminders (SendInvoiceReminders job).
        | days_offset is relative to due_at: negative = before due, positive = after.
        | Final warning defaults to T+2 (day before suspension at T+3).
        */
        'reminders' => [
            'enabled' => (bool) env('COREPANEL_BILLING_REMINDERS_ENABLED', true),
            'schedule' => env('COREPANEL_BILLING_REMINDERS_SCHEDULE', 'daily'),
            'levels' => [
                ['key' => 'before_due_7', 'days_offset' => -7],
                ['key' => 'before_due_3', 'days_offset' => -3],
                ['key' => 'due', 'days_offset' => 0],
                ['key' => 'overdue', 'days_offset' => 1],
                ['key' => 'final_warning', 'days_offset' => 2],
            ],
        ],

        /*
        | Automatic suspension / termination for overdue invoices (ProcessOverdueSuspensions).
        | T+suspend_after_days → suspend linked services; T+terminate_after_days → terminate.
        | Real lifecycle mutations are delegated to OverdueServiceActions (null until services engine).
        */
        'suspension' => [
            'enabled' => (bool) env('COREPANEL_BILLING_SUSPENSION_ENABLED', true),
            'suspend_after_days' => (int) env('COREPANEL_BILLING_SUSPEND_AFTER_DAYS', 3),
            'terminate_after_days' => (int) env('COREPANEL_BILLING_TERMINATE_AFTER_DAYS', 7),
            'skip_if_credit_available' => (bool) env('COREPANEL_BILLING_SUSPENSION_SKIP_CREDIT', true),
            'skip_vip_clients' => (bool) env('COREPANEL_BILLING_SUSPENSION_SKIP_VIP', true),
            'schedule' => env('COREPANEL_BILLING_SUSPENSION_SCHEDULE', 'daily'),
        ],

        /*
        | Manual offline gateway (bank transfer / cheque).
        | Payments stay pending until staff confirms via PaymentService::complete().
        */
        'manual_transfer' => [
            'enabled' => (bool) env('COREPANEL_BILLING_MANUAL_TRANSFER_ENABLED', true),
            'label' => env('COREPANEL_BILLING_MANUAL_TRANSFER_LABEL', 'Bank transfer'),
            'reference_prefix' => env('COREPANEL_BILLING_MANUAL_TRANSFER_REFERENCE_PREFIX', 'PAY'),
            'beneficiary' => env('COREPANEL_BILLING_MANUAL_TRANSFER_BENEFICIARY'),
            'iban' => env('COREPANEL_BILLING_MANUAL_TRANSFER_IBAN'),
            'bic' => env('COREPANEL_BILLING_MANUAL_TRANSFER_BIC'),
            'bank_name' => env('COREPANEL_BILLING_MANUAL_TRANSFER_BANK_NAME'),
            'instructions' => env('COREPANEL_BILLING_MANUAL_TRANSFER_INSTRUCTIONS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Coupon UI toggle and available payment methods for client checkout.
    |
    */

    'checkout' => [
        'coupon_enabled' => (bool) env('COREPANEL_CHECKOUT_COUPON_ENABLED', true),
        'payment_methods' => [
            [
                'key' => 'manual_transfer',
                'label' => 'Bank transfer',
                'enabled' => true,
                'hint' => null,
            ],
            [
                'key' => 'card',
                'label' => 'Credit card',
                'enabled' => false,
                'hint' => 'Coming soon',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RBAC
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

    /*
    |--------------------------------------------------------------------------
    | Provisioning
    |--------------------------------------------------------------------------
    |
    | Queue retry / backoff / uniqueness for ProvisionServiceJob.
    | Definitive failures are stored in provisioning_dead_letters.
    |
    */

    'provisioning' => [
        'tries' => (int) env('COREPANEL_PROVISIONING_TRIES', 3),
        'timeout_seconds' => (int) env('COREPANEL_PROVISIONING_TIMEOUT_SECONDS', 120),
        'unique_for_seconds' => (int) env('COREPANEL_PROVISIONING_UNIQUE_FOR_SECONDS', 3600),
        'backoff_seconds' => array_values(array_filter(array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', (string) env('COREPANEL_PROVISIONING_BACKOFF_SECONDS', '30,60,120')),
        ), static fn (int $value): bool => $value > 0)) ?: [30, 60, 120],
        /*
        | When a product has a node group assigned, provisioning requires an eligible node.
        */
        'require_node_for_assigned_group' => (bool) env('COREPANEL_PROVISIONING_REQUIRE_NODE', true),
        /*
        | Clear orphan mapping / external_id after definitive failure (keeps node_id for retry affinity).
        */
        'rollback_on_failure' => (bool) env('COREPANEL_PROVISIONING_ROLLBACK_ON_FAILURE', true),

        /*
        | Local / test stub provider (no remote API). Disabled by default outside local.
        | fail_operations: list of operations that return Failed (create, suspend, …) or ['*'].
        */
        'stub' => [
            'enabled' => filter_var(
                env('COREPANEL_PROVISIONING_STUB_ENABLED', env('APP_ENV') === 'local'),
                FILTER_VALIDATE_BOOL,
            ),
            'key' => env('COREPANEL_PROVISIONING_STUB_KEY', 'stub'),
            'label' => env('COREPANEL_PROVISIONING_STUB_LABEL', 'Stub Provider'),
            'register_node_provider' => filter_var(
                env('COREPANEL_PROVISIONING_STUB_REGISTER_NODE', true),
                FILTER_VALIDATE_BOOL,
            ),
            'external_id_prefix' => env('COREPANEL_PROVISIONING_STUB_EXTERNAL_PREFIX', 'stub'),
            'hostname_suffix' => env('COREPANEL_PROVISIONING_STUB_HOSTNAME_SUFFIX', '.stub.local'),
            'ip_prefix' => env('COREPANEL_PROVISIONING_STUB_IP_PREFIX', '10.255.0.'),
            'fail_operations' => array_values(array_filter(array_map(
                trim(...),
                explode(',', (string) env('COREPANEL_PROVISIONING_STUB_FAIL_OPERATIONS', '')),
            ))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nodes infrastructure
    |--------------------------------------------------------------------------
    */

    'nodes' => [
        'metrics' => [
            'enabled' => filter_var(
                env('COREPANEL_NODE_METRICS_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
            'schedule' => env('COREPANEL_NODE_METRICS_SCHEDULE', 'everyFiveMinutes'),
        ],
        'allocation' => [
            'require_credentials' => filter_var(
                env('COREPANEL_NODE_ALLOCATION_REQUIRE_CREDENTIALS', false),
                FILTER_VALIDATE_BOOL,
            ),
        ],
        'capacity' => [
            'stale_after_seconds' => (int) env('COREPANEL_NODE_CAPACITY_STALE_AFTER_SECONDS', 600),
        ],
        'health' => [
            'enabled' => filter_var(
                env('COREPANEL_NODE_HEALTH_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
            'schedule' => env('COREPANEL_NODE_HEALTH_SCHEDULE', 'everyMinute'),
            'auto_status' => filter_var(
                env('COREPANEL_NODE_HEALTH_AUTO_STATUS', true),
                FILTER_VALIDATE_BOOL,
            ),
            'degraded_utilization_threshold' => (float) env('COREPANEL_NODE_HEALTH_DEGRADED_THRESHOLD', 0.85),
        ],
        'monitoring' => [
            'history_hours' => (int) env('COREPANEL_NODE_MONITORING_HISTORY_HOURS', 24),
            'bucket_minutes' => (int) env('COREPANEL_NODE_MONITORING_BUCKET_MINUTES', 15),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI theme
    |--------------------------------------------------------------------------
    |
    | Class-strategy dark mode. Preference is stored in localStorage and applied
    | before paint via <x-ui.theme-script />.
    |
    */

    'ui' => [
        'theme' => [
            'default' => env('COREPANEL_THEME_DEFAULT', 'system'),
            'storage_key' => env('COREPANEL_THEME_STORAGE_KEY', 'corepanel.theme'),
        ],

        /*
        | Component showcase at /dev/components.
        | null = enabled only when APP_ENV=local.
        */
        'showcase' => [
            'enabled' => env('COREPANEL_UI_SHOWCASE'),
        ],
    ],

];
