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
    ├── resources/
    │   ├── views/
    │   └── lang/
    ├── database/
    ├── Providers/
    └── module.json
```

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

Known capability values: `server_provider`, `node_provider`, `payment_gateway`,
`notification_channel`, `dns_provider`, `other` (custom strings allowed).

## Rules

- No direct access to the Core database (sandbox)
- Dynamic loading via `ModuleManager`
- Implement `ModuleInterface` (extend `AbstractModule`) when declaring `module`
- `module.json` is required (`name`, `version`, `capabilities`)
