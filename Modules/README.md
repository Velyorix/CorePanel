# Modules

Integration packages for external services and panels.

Modules never replace Core features — they implement Core contracts
(`ServerProviderInterface`, `NodeProviderInterface`, `PaymentGatewayInterface`, etc.).

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

## Rules

- No direct access to the Core database (sandbox)
- Dynamic loading via `ModuleManager`
- `module.json` is required (`name`, `version`, `capabilities`)
