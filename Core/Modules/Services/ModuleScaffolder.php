<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Enums\ModuleScaffoldProfile;
use Core\Modules\Exceptions\ModuleScaffoldException;
use Core\Modules\Support\ModuleName;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/**
 * Creates new module package scaffolds on disk.
 */
class ModuleScaffolder
{
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly ?string $modulesPath = null,
    ) {
    }

    /**
     * @param  array{
     *     key?: string|null,
     *     label?: string|null,
     *     author?: string|null,
     *     email?: string|null,
     *     description?: string|null,
     *     version?: string|null,
     *     profile?: string|null,
     *     capabilities?: list<string>|null
     * }  $options
     * @return array{manifest: ModuleManifest, path: string, files: list<string>, profile: ModuleScaffoldProfile}
     */
    public function make(string $name, array $options = []): array
    {
        $parsed = ModuleName::fromInput(
            $name,
            $options['key'] ?? null,
            $options['label'] ?? null,
        );

        $profile = ModuleScaffoldProfile::fromInput((string) ($options['profile'] ?? ModuleScaffoldProfile::Integration->value));
        $capabilities = $this->resolveCapabilities($profile, $options['capabilities'] ?? null);

        $root = $this->path().DIRECTORY_SEPARATOR.$parsed->directory;

        if (is_dir($root)) {
            throw ModuleScaffoldException::directoryExists($root);
        }

        $this->modules->discover(refresh: true);

        if ($this->modules->has($parsed->key)) {
            throw ModuleScaffoldException::keyExists($parsed->key);
        }

        $author = $this->stringOption($options['author'] ?? null, config('app.name', 'CorePanel'));
        $email = $this->stringOption($options['email'] ?? null, 'dev@example.test');
        $description = $this->stringOption(
            $options['description'] ?? null,
            "{$parsed->label} module package.",
        );
        $version = $this->stringOption($options['version'] ?? null, '1.0.0');

        $written = [];

        File::ensureDirectoryExists($root);
        File::ensureDirectoryExists($root.'/Providers');
        File::ensureDirectoryExists($root.'/routes');
        File::ensureDirectoryExists($root.'/resources/views');
        File::ensureDirectoryExists($root.'/database/migrations');

        $written[] = $this->write($root.'/module.json', $this->manifest(
            parsed: $parsed,
            profile: $profile,
            capabilities: $capabilities,
            version: $version,
            author: $author,
            email: $email,
            description: $description,
        ));
        $written[] = $this->write($root.'/README.md', $this->readme($parsed, $profile, $author));
        $written[] = $this->write($root.'/'.$parsed->directory.'Module.php', $this->moduleClass($parsed, $profile));
        $written[] = $this->write(
            $root.'/Providers/'.$parsed->directory.'ServiceProvider.php',
            $this->serviceProviderClass($parsed, $profile),
        );
        $written[] = $this->write($root.'/routes/web.php', $this->routesStub($parsed));

        foreach ($this->profileFiles($parsed, $profile) as $relative => $contents) {
            $written[] = $this->write($root.'/'.$relative, $contents);
        }

        $manifest = ModuleManifest::fromDirectory($root, $parsed->directory);
        $this->modules->discover(refresh: true);

        return [
            'manifest' => $manifest,
            'path' => $root,
            'files' => $written,
            'profile' => $profile,
        ];
    }

    /**
     * @param  list<string>|null  $extra
     * @return list<string>
     */
    private function resolveCapabilities(ModuleScaffoldProfile $profile, ?array $extra): array
    {
        $capabilities = $profile->defaultCapabilities();

        if ($extra === null) {
            return array_values(array_unique($capabilities));
        }

        foreach ($extra as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                throw new InvalidArgumentException('Capability entries must be non-empty strings.');
            }

            $capabilities[] = trim($capability);
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * @return array<string, string>
     */
    private function profileFiles(ModuleName $parsed, ModuleScaffoldProfile $profile): array
    {
        return match ($profile) {
            ModuleScaffoldProfile::Extension => [
                'Notifications/'.$parsed->studly('NotificationChannel').'.php' => $this->notificationChannelClass($parsed),
            ],
            ModuleScaffoldProfile::PaymentGateway => [
                'Gateways/'.$parsed->studly('PaymentGateway').'.php' => $this->paymentGatewayClass($parsed),
            ],
            ModuleScaffoldProfile::Integration => [],
        };
    }

    private function path(): string
    {
        $configured = $this->modulesPath ?? config('corepanel.modules.path');

        if (! is_string($configured) || $configured === '') {
            return base_path('Modules');
        }

        return $configured;
    }

    private function write(string $path, string $contents): string
    {
        File::ensureDirectoryExists(dirname($path));

        if (File::put($path, $contents) === false) {
            throw ModuleScaffoldException::writeFailed($path);
        }

        return $path;
    }

    private function stringOption(?string $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function manifest(
        ModuleName $parsed,
        ModuleScaffoldProfile $profile,
        array $capabilities,
        string $version,
        string $author,
        string $email,
        string $description,
    ): string {
        $manifest = [
            'name' => $parsed->key,
            'version' => $version,
            'label' => $parsed->label,
            'description' => $description,
            'capabilities' => $capabilities,
            'module' => $parsed->moduleClass(),
            'providers' => [$parsed->serviceProviderClass()],
            'authors' => [
                ['name' => $author, 'email' => $email],
            ],
            'homepage' => config('app.url', 'https://corepanel.org'),
            'license' => 'MIT',
            'requires' => [
                'php' => '>=8.4',
            ],
        ];

        if ($profile === ModuleScaffoldProfile::Extension) {
            $manifest['hooks'] = [
                'events' => ['invoice.paid'],
            ];
            $manifest['permissions'] = [
                [
                    'name' => 'module.'.$parsed->key.'.notifications.view',
                    'description' => 'View '.$parsed->label.' notification logs',
                ],
            ];
        }

        if ($profile === ModuleScaffoldProfile::PaymentGateway) {
            $manifest['gateways'] = [
                $parsed->namespace.'\\Gateways\\'.$parsed->studly('PaymentGateway'),
            ];
        }

        if ($profile === ModuleScaffoldProfile::Integration) {
            $manifest['permissions'] = [
                [
                    'name' => 'module.'.$parsed->key.'.manage',
                    'description' => 'Manage '.$parsed->label.' integration settings',
                ],
            ];
        }

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    private function readme(ModuleName $parsed, ModuleScaffoldProfile $profile, string $author): string
    {
        return <<<MD
# {$parsed->label}

{$profile->label()} scaffolded by `module:make`.

## Enable

```env
COREPANEL_MODULES_ENABLED={$parsed->key}
```

Or install / enable from Admin → Modules.

## Package

- Key: `{$parsed->key}`
- Namespace: `{$parsed->namespace}`
- Author: {$author}

MD;
    }

    private function moduleClass(ModuleName $parsed, ModuleScaffoldProfile $profile): string
    {
        if ($profile === ModuleScaffoldProfile::Extension) {
            return str_replace(
                ['NAMESPACE', 'CLASS', 'KEY', 'LABEL', 'CHANNEL_CLASS'],
                [
                    $parsed->namespace,
                    $parsed->directory.'Module',
                    $parsed->key,
                    $parsed->label,
                    $parsed->studly('NotificationChannel'),
                ],
                <<<'PHP'
<?php

namespace NAMESPACE;

use Core\Billing\Events\InvoicePaid;
use Core\Modules\Contracts\RegistersModuleHooks;
use Core\Modules\Services\ModuleHookRegistry;
use Core\Modules\Support\AbstractModule;
use NAMESPACE\Notifications\CHANNEL_CLASS;

class CLASS extends AbstractModule implements RegistersModuleHooks
{
    public function registerModuleHooks(ModuleHookRegistry $hooks): void
    {
        $hooks->listenEvent(
            'invoice.paid',
            function (InvoicePaid $event): void {
                $this->channel()->deliver('invoice.paid', [
                    'invoice_id' => $event->invoice->id,
                    'client_id' => $event->invoice->client_id,
                ]);
            },
            moduleKey: $this->key(),
        );
    }

    public function boot(): void
    {
        $this->host()->log('info', 'LABEL module booted.', [
            'module' => $this->key(),
            'version' => $this->version(),
        ]);
    }

    private function channel(): CHANNEL_CLASS
    {
        return app(CHANNEL_CLASS::class);
    }
}

PHP
            );
        }

        return str_replace(
            ['NAMESPACE', 'CLASS', 'KEY', 'LABEL'],
            [$parsed->namespace, $parsed->directory.'Module', $parsed->key, $parsed->label],
            <<<'PHP'
<?php

namespace NAMESPACE;

use Core\Modules\Support\AbstractModule;

class CLASS extends AbstractModule
{
    public function boot(): void
    {
        $this->host()->log('info', 'LABEL module booted.', [
            'module' => $this->key(),
            'version' => $this->version(),
        ]);
    }
}

PHP
        );
    }

    private function serviceProviderClass(ModuleName $parsed, ModuleScaffoldProfile $profile): string
    {
        $extraUses = '';
        $extraRegister = '';
        $extraBoot = '';

        if ($profile === ModuleScaffoldProfile::Extension) {
            $channel = $parsed->studly('NotificationChannel');
            $extraUses = "use {$parsed->namespace}\\Notifications\\{$channel};".PHP_EOL;
            $extraRegister = PHP_EOL.'        $this->app->singleton('.$channel.'::class);';
        }

        if ($profile === ModuleScaffoldProfile::PaymentGateway) {
            $gateway = $parsed->studly('PaymentGateway');
            $extraUses = "use {$parsed->namespace}\\Gateways\\{$gateway};".PHP_EOL;
            $extraRegister = PHP_EOL.'        $this->app->singleton('.$gateway.'::class);';
            $extraBoot = PHP_EOL.'        $this->registerPaymentGateway($this->app->make('.$gateway.'::class));';
        }

        return str_replace(
            ['NAMESPACE', 'CLASS', 'KEY', 'EXTRA_USES', 'EXTRA_REGISTER', 'EXTRA_BOOT'],
            [
                $parsed->namespace,
                $parsed->directory.'ServiceProvider',
                $parsed->key,
                $extraUses,
                $extraRegister,
                $extraBoot,
            ],
            <<<'PHP'
<?php

namespace NAMESPACE\Providers;

use Core\Modules\Support\AbstractModuleServiceProvider;
EXTRA_USES
class CLASS extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'KEY';
    }

    public function register(): void
    {EXTRA_REGISTER
    }

    public function boot(): void
    {EXTRA_BOOT
    }
}

PHP
        );
    }

    private function routesStub(ModuleName $parsed): string
    {
        $routeName = 'module.'.$parsed->key.'.status';

        return str_replace(
            ['KEY', 'ROUTE_NAME', 'LABEL'],
            [$parsed->key, $routeName, $parsed->label],
            <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/__KEY/status', static fn () => response()->json([
    'module' => 'KEY',
    'status' => 'ok',
]))->name('ROUTE_NAME');

PHP
        );
    }

    private function notificationChannelClass(ModuleName $parsed): string
    {
        return str_replace(
            ['NAMESPACE', 'CLASS', 'KEY'],
            [$parsed->namespace, $parsed->studly('NotificationChannel'), $parsed->key],
            <<<'PHP'
<?php

namespace NAMESPACE\Notifications;

use Illuminate\Support\Facades\Log;

class CLASS
{
    /** @var list<array{event: string, context: array<string, mixed>}> */
    private array $deliveries = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function deliver(string $event, array $context = []): void
    {
        $this->deliveries[] = ['event' => $event, 'context' => $context];

        Log::info('Module notification delivered.', [
            'module' => 'KEY',
            'event' => $event,
            ...$context,
        ]);
    }

    /**
     * @return list<array{event: string, context: array<string, mixed>}>
     */
    public function deliveries(): array
    {
        return $this->deliveries;
    }

    public function flush(): void
    {
        $this->deliveries = [];
    }
}

PHP
        );
    }

    private function paymentGatewayClass(ModuleName $parsed): string
    {
        return str_replace(
            ['NAMESPACE', 'CLASS', 'KEY', 'LABEL'],
            [$parsed->namespace, $parsed->studly('PaymentGateway'), $parsed->key, $parsed->label],
            <<<'PHP'
<?php

namespace NAMESPACE\Gateways;

use Core\Billing\Contracts\PaymentGateway;
use Core\Billing\DataTransferObjects\PaymentContext;
use Core\Billing\DataTransferObjects\PaymentGatewayResult;
use Core\Billing\DataTransferObjects\PaymentGatewayWebhookResult;
use Core\Billing\Exceptions\UnsupportedGatewayOperationException;
use Core\Billing\Models\Payment;

class CLASS implements PaymentGateway
{
    public function key(): string
    {
        return 'KEY';
    }

    public function label(): string
    {
        return 'LABEL';
    }

    public function createPayment(Payment $payment, PaymentContext $context): PaymentGatewayResult
    {
        return PaymentGatewayResult::pending(
            transactionId: 'KEY-pending-'.$payment->id,
            gatewayReference: 'KEY-ref-'.$payment->id,
            message: 'Replace with provider integration.',
        );
    }

    public function verifyPayment(Payment $payment): PaymentGatewayResult
    {
        return PaymentGatewayResult::pending(
            transactionId: $payment->transaction_id,
            gatewayReference: $payment->gateway_reference,
        );
    }

    public function refundPayment(Payment $payment, string $amount): PaymentGatewayResult
    {
        throw UnsupportedGatewayOperationException::forOperation($this->key(), 'refund');
    }

    public function handleWebhook(array $payload, array $headers): PaymentGatewayWebhookResult
    {
        throw UnsupportedGatewayOperationException::forOperation($this->key(), 'webhook');
    }
}

PHP
        );
    }
}
