# Modules

Integration packages for external services and panels.

Modules never replace Core features — they implement Core contracts
(`ServerProviderInterface`, `NodeProviderInterface`, `PaymentGatewayInterface`, etc.)
and expose a `ModuleInterface` entrypoint declared in `module.json`.

## Examples

- Pterodactyl, WISP (game hosting)
- Proxmox, VMware (virtualisation)
- cPanel, Plesk, DirectAdmin (web hosting)
- Stripe, PayPal, Mollie (payments)

## Package layout

```txt
Modules/
└── ExampleModule/
    ├── config/
    ├── src/
    ├── routes/
    │   ├── web.php
    │   ├── admin.php
    │   └── api.php
    ├── resources/
    │   ├── views/
    │   └── lang/
    ├── database/
    │   └── migrations/
    ├── Providers/
    └── module.json
```

On load, `ModuleResourceLoader` auto-registers:

- `routes/*.php` (middleware by filename: web/admin/client/api)
- `resources/views` as Blade namespace `{moduleKey}::`
- `database/migrations` into the migrator paths

## `module.json` schema

Required fields: `name`, `version`, `capabilities`.

```json
{
  "name": "example",
  "version": "1.0.0",
  "label": "Example Provider",
  "description": "Demo integration module",
  "capabilities": ["server_provider", "node_provider"],
  "module": "Modules\\Example\\ExampleModule",
  "providers": [
    "Modules\\Example\\Providers\\ExampleServiceProvider"
  ],
  "authors": [
    { "name": "Velyorix", "email": "dev@example.test" }
  ],
  "homepage": "https://example.test",
  "license": "MIT",
  "requires": {
    "corepanel": ">=1.0.0",
    "php": ">=8.4"
  },
  "permissions": [
    "module.example.server.create",
    {
      "name": "module.example.server.restart",
      "description": "Restart servers"
    }
  ]
}
```

Known capability values: `extension`, `server_provider`, `node_provider`, `payment_gateway`,
`notification_channel`, `dns_provider`, `other` (custom strings allowed).

Extension modules (lightweight plugins) declare `extension` and may subscribe to Core hooks:

```json
{
  "name": "discord_notify",
  "version": "1.0.0",
  "capabilities": ["extension", "notification_channel"],
  "hooks": {
    "events": ["invoice.paid", "order.paid"],
    "hooks": ["admin.navigation.build"],
    "filters": ["invoice.email.subject"]
  }
}
```

Rules:

- `[notification_channel]` requires `[extension]`
- A non-empty `[hooks]` object requires `[extension]`
- Hook names use lowercase dotted identifiers (e.g. `invoice.paid`)

## Payment gateways

Modules can inject billing payment gateways in two ways:

1. **Declarative** — list gateway classes in `module.json` → `gateways` (must implement
   `Core\Billing\Contracts\PaymentGateway`). They are registered into `GatewayManager`
   when the module is loaded.
2. **Imperative** — from a module `ServiceProvider`, call
   `$this->registerPaymentGateway($gateway)` (helper on `AbstractModuleServiceProvider`).

```json
{
  "name": "stripe_billing",
  "version": "1.0.0",
  "capabilities": ["payment_gateway"],
  "gateways": [
    "Modules\\StripeBilling\\StripePaymentGateway"
  ],
  "providers": [
    "Modules\\StripeBilling\\Providers\\StripeBillingServiceProvider"
  ]
}
```

See also `ExampleExtension/` for a lightweight extension module that listens to Core events.

## Rules

- No direct access to Core database tables (sandbox)
- Module-owned tables must use the prefix `module_{key}_`
- Use `ModuleHostApi` for mediated Core operations (config, logging, permissions)
- Install registry: `installed_modules` (version, enabled, checksum, signature)
- Integrity: declare `checksum` in `module.json` or `module.sha256` (SHA-256 of package)
- Optional `signature` (HMAC of checksum); set `COREPANEL_MODULES_SIGNATURE_REQUIRED=true` to enforce
- Dynamic loading via `ModuleManager`
- Declare Laravel providers in `module.json` → `providers` (registered on load)
- Prefer extending `AbstractModuleServiceProvider`
- Routes / views / migrations under the package are auto-loaded on module load
- Implement `ModuleInterface` (extend `AbstractModule`) when declaring `module`
- `module.json` is required (`name`, `version`, `capabilities`)

## Artisan scaffolding

```bash
php artisan module:make "My Module" --profile=integration
php artisan module:make "Discord Notify" --profile=extension
php artisan module:make "Stripe Billing" --profile=payment_gateway

php artisan module:make:migration create_items_table --module=my_module
php artisan module:make:model Item --module=my_module --migration
php artisan module:make:controller Status --module=my_module --admin
php artisan module:make:service Status --module=my_module
php artisan module:make:event StatusChecked --module=my_module
php artisan module:make:factory Item --module=my_module
php artisan module:make:seeder ItemSeeder --module=my_module
php artisan module:make:listener OnStatusChecked --module=my_module --event=StatusChecked
php artisan module:make:trait Cacheable --module=my_module
php artisan module:make:provider BillingServiceProvider --module=my_module
php artisan module:make:gateway Stripe --module=my_module
php artisan module:make:command Sync --module=my_module
php artisan module:make:request StoreItem --module=my_module
php artisan module:make:policy Item --module=my_module

php artisan module:list
php artisan module:list --installed --enabled
```

Profiles:

- `integration` — generic provider package (`other` capability by default)
- `extension` — hook listener + notification channel stub
- `payment_gateway` — gateway class + manifest `gateways` entry
