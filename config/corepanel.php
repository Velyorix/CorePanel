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
        'store_url' => env('COREPANEL_ORG_STORE_URL', 'https://corepanel.org'),
        'timeout_seconds' => (int) env('COREPANEL_ORG_TIMEOUT_SECONDS', 10),
        /*
        | Optional override for the built-in marketplace catalogue Bearer token.
        | Leave empty in production — MarketplaceClient falls back to the shipped
        | marketplace:read token (see MarketplaceCatalogCredentials).
        */
        'api_token' => env('COREPANEL_ORG_API_TOKEN'),
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
    | Marketplace
    |--------------------------------------------------------------------------
    |
    | Catalogue / versions are fetched via MarketplaceClient against corepanel.org.
    | Caching and install flows are layered on top of this client.
    |
    */

    'marketplace' => [
        'enabled' => filter_var(env('COREPANEL_MARKETPLACE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'cache' => [
            'enabled' => filter_var(env('COREPANEL_MARKETPLACE_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
            'store' => env('COREPANEL_MARKETPLACE_CACHE_STORE', 'redis'),
            'prefix' => env('COREPANEL_MARKETPLACE_CACHE_PREFIX', 'corepanel.marketplace'),
            'ttl_seconds' => (int) env('COREPANEL_MARKETPLACE_CACHE_TTL', 3600),
        ],
        'entitlements' => [
            'enforce' => filter_var(env('COREPANEL_MARKETPLACE_ENTITLEMENTS_ENFORCE', true), FILTER_VALIDATE_BOOL),
            'allow_free_without_entitlement' => filter_var(
                env('COREPANEL_MARKETPLACE_ALLOW_FREE_WITHOUT_ENTITLEMENT', true),
                FILTER_VALIDATE_BOOL,
            ),
        ],
        'compatibility' => [
            'enforce' => filter_var(env('COREPANEL_MARKETPLACE_COMPATIBILITY_ENFORCE', true), FILTER_VALIDATE_BOOL),
        ],
        'updates' => [
            'enabled' => filter_var(env('COREPANEL_MARKETPLACE_UPDATES_ENABLED', true), FILTER_VALIDATE_BOOL),
            'schedule' => env('COREPANEL_MARKETPLACE_UPDATES_SCHEDULE', 'daily'),
            'cache_ttl_seconds' => (int) env('COREPANEL_MARKETPLACE_UPDATES_CACHE_TTL', 86400),
        ],
        'integrity' => [
            'enforce_checksum' => filter_var(
                env('COREPANEL_MARKETPLACE_INTEGRITY_ENFORCE_CHECKSUM', true),
                FILTER_VALIDATE_BOOL,
            ),
            'verify_signature' => filter_var(
                env('COREPANEL_MARKETPLACE_INTEGRITY_VERIFY_SIGNATURE', true),
                FILTER_VALIDATE_BOOL,
            ),
            'enforce_signature' => filter_var(
                env('COREPANEL_MARKETPLACE_INTEGRITY_ENFORCE_SIGNATURE', true),
                FILTER_VALIDATE_BOOL,
            ),
            'signature_secret' => env('COREPANEL_MARKETPLACE_INTEGRITY_SIGNATURE_SECRET'),
        ],
        'install' => [
            'temp_path' => env('COREPANEL_MARKETPLACE_TEMP_PATH', storage_path('app/marketplace/tmp')),
            'download_timeout_seconds' => (int) env('COREPANEL_MARKETPLACE_DOWNLOAD_TIMEOUT', 120),
        ],
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
            'label' => env('COREPANEL_BILLING_MANUAL_TRANSFER_LABEL', 'Bank transfer / cheque'),
            'reference_prefix' => env('COREPANEL_BILLING_MANUAL_TRANSFER_REFERENCE_PREFIX', 'PAY'),
            'beneficiary' => env('COREPANEL_BILLING_MANUAL_TRANSFER_BENEFICIARY'),
            'iban' => env('COREPANEL_BILLING_MANUAL_TRANSFER_IBAN'),
            'bic' => env('COREPANEL_BILLING_MANUAL_TRANSFER_BIC'),
            'bank_name' => env('COREPANEL_BILLING_MANUAL_TRANSFER_BANK_NAME'),
            'instructions' => env('COREPANEL_BILLING_MANUAL_TRANSFER_INSTRUCTIONS'),
        ],

        /*
        | Prepaid client wallet. When enabled, available credit is applied to the
        | invoice before charging the selected payment gateway.
        */
        'client_credit' => [
            'auto_apply_on_pay' => (bool) env('COREPANEL_BILLING_CLIENT_CREDIT_AUTO_APPLY', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Coupon UI toggle. Available payment methods are resolved dynamically from
    | enabled gateways registered in GatewayManager (admin Settings → Payment gateways).
    |
    */

    'checkout' => [
        'coupon_enabled' => (bool) env('COREPANEL_CHECKOUT_COUPON_ENABLED', true),
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
    | Services
    |--------------------------------------------------------------------------
    */

    'services' => [
        'sync' => [
            'enabled' => filter_var(
                env('COREPANEL_SERVICE_SYNC_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
            'schedule' => env('COREPANEL_SERVICE_SYNC_SCHEDULE', 'everyFiveMinutes'),
            'compare' => [
                'status' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_COMPARE_STATUS', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'ip_address' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_COMPARE_IP', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'hostname' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_COMPARE_HOSTNAME', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'external_id' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_COMPARE_EXTERNAL_ID', true),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
            'resolve' => [
                'enabled' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_RESOLVE_ENABLED', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'external_deleted' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_RESOLVE_EXTERNAL_DELETED', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'status_mismatch' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_RESOLVE_STATUS', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'ip_address' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_RESOLVE_IP', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'hostname' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_RESOLVE_HOSTNAME', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'external_id' => filter_var(
                    env('COREPANEL_SERVICE_SYNC_RESOLVE_EXTERNAL_ID', true),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync logging & admin alerts
    |--------------------------------------------------------------------------
    */

    'sync' => [
        'logs' => [
            'service' => [
                'enabled' => filter_var(
                    env('COREPANEL_SYNC_LOG_SERVICE', true),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
            'node' => [
                'enabled' => filter_var(
                    env('COREPANEL_SYNC_LOG_NODE', true),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
        ],
        'alerts' => [
            'enabled' => filter_var(
                env('COREPANEL_SYNC_ALERTS_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
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
        'sync' => [
            'enabled' => filter_var(
                env('COREPANEL_NODE_SYNC_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
            'schedule' => env('COREPANEL_NODE_SYNC_SCHEDULE', 'everyTenMinutes'),
        ],
        'allocation' => [
            'require_credentials' => filter_var(
                env('COREPANEL_NODE_ALLOCATION_REQUIRE_CREDENTIALS', false),
                FILTER_VALIDATE_BOOL,
            ),
            'load_balancing' => [
                'enabled' => filter_var(
                    env('COREPANEL_NODE_LOAD_BALANCING_ENABLED', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'default_weight' => (int) env('COREPANEL_NODE_LOAD_BALANCING_DEFAULT_WEIGHT', 100),
                'utilization_band' => (float) env('COREPANEL_NODE_LOAD_BALANCING_UTILIZATION_BAND', 0.15),
                'use_reliability_history' => filter_var(
                    env('COREPANEL_NODE_LOAD_BALANCING_USE_RELIABILITY', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'reliability_hours' => (int) env('COREPANEL_NODE_LOAD_BALANCING_RELIABILITY_HOURS', 24),
                'unknown_reliability_factor' => (float) env('COREPANEL_NODE_LOAD_BALANCING_UNKNOWN_RELIABILITY', 0.85),
                'state_ttl_seconds' => (int) env('COREPANEL_NODE_LOAD_BALANCING_STATE_TTL', 3600),
                'cache_prefix' => env('COREPANEL_NODE_LOAD_BALANCING_CACHE_PREFIX', 'nodes.load_balancing'),
                'uptime_factors' => [
                    'online' => (float) env('COREPANEL_NODE_LOAD_BALANCING_UPTIME_ONLINE', 1.0),
                    'degraded' => (float) env('COREPANEL_NODE_LOAD_BALANCING_UPTIME_DEGRADED', 0.5),
                    'unknown' => (float) env('COREPANEL_NODE_LOAD_BALANCING_UPTIME_UNKNOWN', 0.85),
                    'offline' => (float) env('COREPANEL_NODE_LOAD_BALANCING_UPTIME_OFFLINE', 0.1),
                ],
            ],
        ],
        'overload' => [
            'enabled' => filter_var(
                env('COREPANEL_NODE_OVERLOAD_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
            'defer_on_overload' => filter_var(
                env('COREPANEL_NODE_OVERLOAD_DEFER', true),
                FILTER_VALIDATE_BOOL,
            ),
            'queue_delay_seconds' => (int) env('COREPANEL_NODE_OVERLOAD_QUEUE_DELAY', 60),
            'utilization_threshold' => (float) env('COREPANEL_NODE_OVERLOAD_UTILIZATION_THRESHOLD', 0.85),
            'fallback_utilization_threshold' => (float) env('COREPANEL_NODE_OVERLOAD_FALLBACK_UTILIZATION_THRESHOLD', 0.95),
            'service_fill_threshold' => (float) env('COREPANEL_NODE_OVERLOAD_SERVICE_FILL_THRESHOLD', 0.95),
            'fallback_service_fill_threshold' => (float) env('COREPANEL_NODE_OVERLOAD_FALLBACK_SERVICE_FILL_THRESHOLD', 0.98),
            'block_degraded' => filter_var(
                env('COREPANEL_NODE_OVERLOAD_BLOCK_DEGRADED', true),
                FILTER_VALIDATE_BOOL,
            ),
            'allow_degraded_fallback' => filter_var(
                env('COREPANEL_NODE_OVERLOAD_ALLOW_DEGRADED_FALLBACK', true),
                FILTER_VALIDATE_BOOL,
            ),
            'respect_capacity_available_flag' => filter_var(
                env('COREPANEL_NODE_OVERLOAD_RESPECT_CAPACITY_AVAILABLE', true),
                FILTER_VALIDATE_BOOL,
            ),
            'fallback_node_ids' => array_values(array_filter(array_map(
                static fn (string $value): int => (int) trim($value),
                explode(',', (string) env('COREPANEL_NODE_OVERLOAD_FALLBACK_NODE_IDS', '')),
            ), static fn (int $value): bool => $value > 0)),
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
        'failover' => [
            'enabled' => filter_var(
                env('COREPANEL_NODE_FAILOVER_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
            'auto_reassign' => filter_var(
                env('COREPANEL_NODE_FAILOVER_AUTO_REASSIGN', true),
                FILTER_VALIDATE_BOOL,
            ),
            'reinstall_on_provider' => filter_var(
                env('COREPANEL_NODE_FAILOVER_REINSTALL_ON_PROVIDER', true),
                FILTER_VALIDATE_BOOL,
            ),
            'eligible_statuses' => array_values(array_filter(array_map(
                trim(...),
                explode(',', (string) env(
                    'COREPANEL_NODE_FAILOVER_ELIGIBLE_STATUSES',
                    'active,suspended',
                )),
            ))),
        ],
        'clusters' => [
            'prefer_peers_on_failover' => filter_var(
                env('COREPANEL_NODE_CLUSTERS_PREFER_PEERS', true),
                FILTER_VALIDATE_BOOL,
            ),
        ],
        'ssh' => [
            'port' => (int) env('COREPANEL_NODE_SSH_PORT', 22),
            'timeout' => (int) env('COREPANEL_NODE_SSH_TIMEOUT', 10),
            'host_key_policy' => env('COREPANEL_NODE_SSH_HOST_KEY_POLICY', 'accept_new'),
            'known_hosts_path' => env(
                'COREPANEL_NODE_SSH_KNOWN_HOSTS_PATH',
                storage_path('app/nodes/ssh_known_hosts'),
            ),
        ],
        'security' => [
            'audit' => [
                'enabled' => filter_var(
                    env('COREPANEL_NODE_AUDIT_ENABLED', true),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
            'tls' => [
                'required' => filter_var(
                    env('COREPANEL_NODE_TLS_REQUIRED', env('APP_ENV') !== 'local'),
                    FILTER_VALIDATE_BOOL,
                ),
                'verify_ssl' => filter_var(
                    env('COREPANEL_NODE_VERIFY_SSL', env('APP_ENV') !== 'local'),
                    FILTER_VALIDATE_BOOL,
                ),
                'ca_bundle' => env('COREPANEL_NODE_CA_BUNDLE'),
                'timeout_seconds' => (int) env('COREPANEL_NODE_TLS_TIMEOUT_SECONDS', 15),
            ],
            'ip_whitelist' => [
                'enabled' => filter_var(
                    env('COREPANEL_NODE_IP_WHITELIST_ENABLED', false),
                    FILTER_VALIDATE_BOOL,
                ),
                'allowed' => array_values(array_filter(array_map(
                    static fn (string $value): string => trim($value),
                    explode(',', (string) env('COREPANEL_NODE_IP_WHITELIST', '')),
                ), static fn (string $value): bool => $value !== '')),
                'allow_private' => filter_var(
                    env('COREPANEL_NODE_IP_WHITELIST_ALLOW_PRIVATE', true),
                    FILTER_VALIDATE_BOOL,
                ),
                'resolve_hostnames' => filter_var(
                    env('COREPANEL_NODE_IP_WHITELIST_RESOLVE_HOSTNAMES', true),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
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

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | Dynamic integration packages under Modules/. Each package must ship a
    | module.json manifest (name, version, capabilities).
    |
    */

    'modules' => [
        'path' => env('COREPANEL_MODULES_PATH', base_path('Modules')),
        'state_path' => env('COREPANEL_MODULES_STATE_PATH', storage_path('app/modules/enabled.json')),
        'enabled' => array_values(array_filter(array_map(
            static fn (string $key): string => trim($key),
            explode(',', (string) env('COREPANEL_MODULES_ENABLED', '')),
        ))),
        'auto_load_enabled' => filter_var(
            env('COREPANEL_MODULES_AUTO_LOAD', true),
            FILTER_VALIDATE_BOOL,
        ),
        'sandbox' => [
            'enabled' => filter_var(
                env('COREPANEL_MODULES_SANDBOX', true),
                FILTER_VALIDATE_BOOL,
            ),
            /*
            | Module-owned tables must use: module_{key}_*
            | Example: module_pterodactyl_nodes
            */
            'module_table_prefix' => env('COREPANEL_MODULES_TABLE_PREFIX', 'module_'),
            'ignored_tables' => [
                'sqlite_master',
                'sqlite_sequence',
                'sqlite_temp_master',
                'main',
            ],
        ],
        'resources' => [
            'routes' => filter_var(env('COREPANEL_MODULES_LOAD_ROUTES', true), FILTER_VALIDATE_BOOL),
            'views' => filter_var(env('COREPANEL_MODULES_LOAD_VIEWS', true), FILTER_VALIDATE_BOOL),
            'migrations' => filter_var(env('COREPANEL_MODULES_LOAD_MIGRATIONS', true), FILTER_VALIDATE_BOOL),
            'route_middleware' => [
                'web.php' => ['web'],
                'admin.php' => ['admin'],
                'client.php' => ['client'],
                'api.php' => ['api'],
            ],
        ],
        'signature' => [
            'required' => filter_var(
                env('COREPANEL_MODULES_SIGNATURE_REQUIRED', false),
                FILTER_VALIDATE_BOOL,
            ),
            'verify_on_load' => filter_var(
                env('COREPANEL_MODULES_VERIFY_ON_LOAD', true),
                FILTER_VALIDATE_BOOL,
            ),
            'secret' => env('COREPANEL_MODULES_SIGNATURE_SECRET'),
        ],
        'hooks' => [
            'laravel_events' => [
                'invoice.paid' => \Core\Billing\Events\InvoicePaid::class,
                'order.paid' => \Core\Orders\Events\OrderPaid::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Themes
    |--------------------------------------------------------------------------
    |
    | Extension themes control appearance (layouts, views, assets). Packages live
    | under Themes/{name}/ with a theme.json manifest and resources/ subtree.
    |
    */

    'themes' => [
        'path' => env('COREPANEL_THEMES_PATH', base_path('Themes')),
        'default' => env('COREPANEL_THEME_DEFAULT', 'default'),
        'auto_load_active' => filter_var(
            env('COREPANEL_THEMES_AUTO_LOAD', true),
            FILTER_VALIDATE_BOOL,
        ),
        'override_module_views' => filter_var(
            env('COREPANEL_THEMES_OVERRIDE_MODULES', true),
            FILTER_VALIDATE_BOOL,
        ),
        'vite' => [
            'core_entries' => [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            'binary' => env('COREPANEL_VITE_BINARY', 'npx'),
            'package' => env('COREPANEL_VITE_PACKAGE', 'vite'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Support tickets
    |--------------------------------------------------------------------------
    |
    | Ticket public references use billing_sequences (name=ticket) under lock.
    | Default format: TK-YYYY-NNNNN
    |
    */

    'tickets' => [
        'numbering' => [
            'prefix' => env('COREPANEL_TICKETS_NUMBER_PREFIX', 'TK'),
            'padding' => (int) env('COREPANEL_TICKETS_NUMBER_PADDING', 5),
            'include_year' => filter_var(
                env('COREPANEL_TICKETS_NUMBER_INCLUDE_YEAR', true),
                FILTER_VALIDATE_BOOL,
            ),
            'reset_yearly' => filter_var(
                env('COREPANEL_TICKETS_NUMBER_RESET_YEARLY', true),
                FILTER_VALIDATE_BOOL,
            ),
            'separator' => env('COREPANEL_TICKETS_NUMBER_SEPARATOR', '-'),
        ],

        /*
        | Secure attachment uploads (private disk, allowlisted types).
        */
        'attachments' => [
            'disk' => env('COREPANEL_TICKETS_ATTACHMENTS_DISK', 'local'),
            'path_prefix' => env('COREPANEL_TICKETS_ATTACHMENTS_PATH', 'tickets'),
            'max_files' => (int) env('COREPANEL_TICKETS_ATTACHMENTS_MAX_FILES', 5),
            'max_kilobytes' => (int) env('COREPANEL_TICKETS_ATTACHMENTS_MAX_KB', 5120),
            'allowed_extensions' => [
                'pdf',
                'png',
                'jpg',
                'jpeg',
                'gif',
                'webp',
                'txt',
                'csv',
                'zip',
                'doc',
                'docx',
            ],
            'allowed_mimes' => [
                'application/pdf',
                'image/png',
                'image/jpeg',
                'image/gif',
                'image/webp',
                'text/plain',
                'text/csv',
                'application/zip',
                'application/x-zip-compressed',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ],

        /*
        | Anti-spam limits for client ticket create / reply actions.
        */
        'rate_limit' => [
            'create' => [
                'max_attempts' => (int) env('COREPANEL_TICKETS_CREATE_MAX_ATTEMPTS', 5),
                'decay_seconds' => (int) env('COREPANEL_TICKETS_CREATE_DECAY_SECONDS', 3600),
            ],
            'reply' => [
                'max_attempts' => (int) env('COREPANEL_TICKETS_REPLY_MAX_ATTEMPTS', 20),
                'decay_seconds' => (int) env('COREPANEL_TICKETS_REPLY_DECAY_SECONDS', 3600),
            ],
        ],

        /*
        | Mail notifications when tickets are opened or receive replies.
        */
        'notifications' => [
            'enabled' => filter_var(
                env('COREPANEL_TICKETS_NOTIFICATIONS_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Multi-channel delivery (mail, database) via NotificationService.
    | Queue mode dispatches Laravel notifications that implement ShouldQueue.
    |
    */

    'notifications' => [
        'enabled' => filter_var(
            env('COREPANEL_NOTIFICATIONS_ENABLED', true),
            FILTER_VALIDATE_BOOL,
        ),
        'default_channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('COREPANEL_NOTIFICATIONS_DEFAULT_CHANNELS', 'mail,database')),
        ))),
        'queue_by_default' => filter_var(
            env('COREPANEL_NOTIFICATIONS_QUEUE', false),
            FILTER_VALIDATE_BOOL,
        ),
        'preference_channels' => ['mail', 'database'],
        'preference_categories' => ['billing', 'tickets', 'services'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    |
    | Runtime key/value store (DB) with optional cache and encrypted values.
    | Technical defaults stay in config/corepanel.php; business overrides use
    | SettingsService.
    |
    */

    'settings' => [
        'cache' => [
            'enabled' => filter_var(
                env('COREPANEL_SETTINGS_CACHE_ENABLED', true),
                FILTER_VALIDATE_BOOL,
            ),
            'store' => env('COREPANEL_SETTINGS_CACHE_STORE', env('CACHE_STORE', 'file')),
            'prefix' => env('COREPANEL_SETTINGS_CACHE_PREFIX', 'corepanel.settings'),
            'ttl_seconds' => (int) env('COREPANEL_SETTINGS_CACHE_TTL', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public REST API
    |--------------------------------------------------------------------------
    |
    | Versioned routes live under /api/{prefix}. Auth, scopes, the JSON
    | response envelope ({ data, meta } / { error }), optional global
    | rate limiting (X-RateLimit-* headers), and list pagination/filters
    | (page, per_page, q, status, sort, dir, …) are applied on /api/v1.
    |
    */

    'api' => [
        'version' => env('COREPANEL_API_VERSION', 'v1'),
        'prefix' => env('COREPANEL_API_PREFIX', 'v1'),
        'request_id_header' => env('COREPANEL_API_REQUEST_ID_HEADER', 'X-Request-Id'),
        'version_header' => env('COREPANEL_API_VERSION_HEADER', 'X-Api-Version'),
        'token_prefix' => env('COREPANEL_API_TOKEN_PREFIX', 'cpat_'),
        'token_entropy_length' => (int) env('COREPANEL_API_TOKEN_ENTROPY_LENGTH', 40),
        'scopes' => [
            '*',
            'api.me',
            'api.client.read',
            'api.client.*',
            'api.service.read',
            'api.service.write',
            'api.service.*',
            'api.invoice.read',
            'api.invoice.write',
            'api.invoice.*',
            'api.ticket.read',
            'api.ticket.write',
            'api.ticket.*',
            'api.node.read',
            'api.node.*',
        ],
        'rate_limit' => [
            'enabled' => filter_var(
                env('COREPANEL_API_RATE_LIMIT_ENABLED', false),
                FILTER_VALIDATE_BOOL,
            ),
            'max_attempts' => (int) env('COREPANEL_API_RATE_LIMIT_MAX', 60),
            'decay_seconds' => (int) env('COREPANEL_API_RATE_LIMIT_DECAY', 60),
        ],
        'pagination' => [
            'default_per_page' => (int) env('COREPANEL_API_DEFAULT_PER_PAGE', 20),
            'max_per_page' => (int) env('COREPANEL_API_MAX_PER_PAGE', 100),
        ],
    ],

];
