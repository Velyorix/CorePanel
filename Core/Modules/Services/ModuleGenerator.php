<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleScaffoldException;
use Core\Modules\Support\ModuleManifestWriter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Generates module files inside an existing module package.
 */
class ModuleGenerator
{
    public function __construct(
        private readonly ModuleManager $modules,
    ) {
    }

    public function resolveModule(?string $moduleKey): ModuleManifest
    {
        if (is_string($moduleKey) && trim($moduleKey) !== '') {
            return $this->modules->findOrFail(trim($moduleKey));
        }

        throw ModuleScaffoldException::moduleRequired();
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeMigration(ModuleManifest $module, string $name): array
    {
        $tableSuffix = $this->migrationTableSuffix($name);
        $table = 'module_'.$module->key.'_'.$tableSuffix;
        $filename = date('Y_m_d_His').'_create_'.$table.'_table.php';
        $absolute = $module->path
            .DIRECTORY_SEPARATOR
            .'database'
            .DIRECTORY_SEPARATOR
            .'migrations'
            .DIRECTORY_SEPARATOR
            .$filename;

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['TABLE_NAME', 'CLASS_COMMENT'],
            [$table, $tableSuffix],
            <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TABLE_NAME', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TABLE_NAME');
    }
};

PHP
        );

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeModel(ModuleManifest $module, string $name, bool $withMigration = false): array
    {
        $className = Str::studly($name);
        $tableSuffix = Str::snake($className);
        $table = 'module_'.$module->key.'_'.$tableSuffix;
        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'TABLE'],
            [$namespace, $className, $table],
            <<<'PHP'
<?php

namespace NAMESPACE\Models;

use Illuminate\Database\Eloquent\Model;

class CLASS extends Model
{
    protected $table = 'TABLE';

    protected $fillable = [];
}

PHP
        );

        $result = ['path' => $this->write($absolute, $contents)];

        if ($withMigration) {
            $migration = $this->makeMigration($module, 'create_'.$tableSuffix.'_table');
            $result['extras']['Migration'] = $migration['path'];
        }

        return $result;
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeController(
        ModuleManifest $module,
        string $name,
        ?string $routeScope = null,
    ): array {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'MODULE_KEY'],
            [$namespace, $className, $module->key],
            <<<'PHP'
<?php

namespace NAMESPACE\Http\Controllers;

use Illuminate\Http\JsonResponse;

class CLASS
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'module' => 'MODULE_KEY',
            'controller' => self::class,
        ]);
    }
}

PHP
        );

        $result = ['path' => $this->write($absolute, $contents)];

        if ($routeScope !== null) {
            $routeFile = $this->appendControllerRoute($module, $namespace, $className, $routeScope);
            $result['extras']['Route'] = $routeFile;
        }

        return $result;
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeService(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Service')) {
            $className .= 'Service';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'MODULE_KEY'],
            [$namespace, $className, $module->key],
            <<<'PHP'
<?php

namespace NAMESPACE\Services;

class CLASS
{
    public function __construct(
        private readonly string $moduleKey = 'MODULE_KEY',
    ) {
    }

    public function moduleKey(): string
    {
        return $this->moduleKey;
    }
}

PHP
        );

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeEvent(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Events'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'MODULE_KEY'],
            [$namespace, $className, $module->key],
            <<<'PHP'
<?php

namespace NAMESPACE\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CLASS
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $moduleKey = 'MODULE_KEY',
    ) {
    }
}

PHP
        );

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeFactory(ModuleManifest $module, string $name): array
    {
        $modelClass = Str::studly($name);
        $factoryClass = $modelClass.'Factory';
        $namespace = $this->namespaceFor($module);
        $absolute = $module->path
            .DIRECTORY_SEPARATOR
            .'Database'
            .DIRECTORY_SEPARATOR
            .'Factories'
            .DIRECTORY_SEPARATOR
            .$factoryClass
            .'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'FACTORY', 'MODEL'],
            [$namespace, $factoryClass, $modelClass],
            <<<'PHP'
<?php

namespace NAMESPACE\Database\Factories;

use NAMESPACE\Models\MODEL;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MODEL>
 */
class FACTORY extends Factory
{
    protected $model = MODEL::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}

PHP
        );

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeSeeder(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Seeder')) {
            $className .= 'Seeder';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path
            .DIRECTORY_SEPARATOR
            .'Database'
            .DIRECTORY_SEPARATOR
            .'Seeders'
            .DIRECTORY_SEPARATOR
            .$className
            .'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'MODULE_KEY'],
            [$namespace, $className, $module->key],
            <<<'PHP'
<?php

namespace NAMESPACE\Database\Seeders;

use Illuminate\Database\Seeder;

class CLASS extends Seeder
{
    public function run(): void
    {
        // Seed module data for MODULE_KEY.
    }
}

PHP
        );

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeListener(ModuleManifest $module, string $name, ?string $event = null): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Listener')) {
            $className .= 'Listener';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Listeners'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $eventClass = $this->resolveEventClass($module, $event);

        if ($eventClass === null) {
            $contents = str_replace(
                ['NAMESPACE', 'CLASS'],
                [$namespace, $className],
                <<<'PHP'
<?php

namespace NAMESPACE\Listeners;

class CLASS
{
    public function handle(object $event): void
    {
        //
    }
}

PHP
            );
        } else {
            $eventShort = class_basename($eventClass);

            $contents = str_replace(
                ['NAMESPACE', 'CLASS', 'EVENT', 'EVENT_SHORT'],
                [$namespace, $className, $eventClass, $eventShort],
                <<<'PHP'
<?php

namespace NAMESPACE\Listeners;

use EVENT;

class CLASS
{
    public function handle(EVENT_SHORT $event): void
    {
        //
    }
}

PHP
            );
        }

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeTrait(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Trait')) {
            $className .= 'Trait';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Traits'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'CLASS'],
            [$namespace, $className],
            <<<'PHP'
<?php

namespace NAMESPACE\Traits;

trait CLASS
{
}

PHP
        );

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeProvider(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'ServiceProvider')) {
            $className .= 'ServiceProvider';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Providers'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'KEY'],
            [$namespace, $className, $module->key],
            <<<'PHP'
<?php

namespace NAMESPACE\Providers;

use Core\Modules\Support\AbstractModuleServiceProvider;

class CLASS extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'KEY';
    }
}

PHP
        );

        $fqcn = $namespace.'\\Providers\\'.$className;
        $manifestUpdated = ModuleManifestWriter::addProvider($module->path, $fqcn);

        $result = ['path' => $this->write($absolute, $contents)];

        if ($manifestUpdated) {
            $result['extras']['Manifest'] = 'providers[] updated';
        }

        return $result;
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeGateway(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Gateway')) {
            $className .= 'Gateway';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Gateways'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $gatewayKey = $module->key.'_'.Str::snake($className);
        $label = Str::headline(str_replace('_', ' ', $gatewayKey));

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'KEY', 'LABEL'],
            [$namespace, $className, $gatewayKey, $label],
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

        $fqcn = $namespace.'\\Gateways\\'.$className;
        $manifestUpdated = ModuleManifestWriter::addGateway($module->path, $fqcn);

        $result = ['path' => $this->write($absolute, $contents)];

        if ($manifestUpdated) {
            $result['extras']['Manifest'] = 'gateways[] updated';
        }

        return $result;
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeCommand(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Command')) {
            $className .= 'Command';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path
            .DIRECTORY_SEPARATOR
            .'Console'
            .DIRECTORY_SEPARATOR
            .'Commands'
            .DIRECTORY_SEPARATOR
            .$className
            .'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $signature = $module->key.':'.Str::kebab(str_replace('Command', '', $className));

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'SIGNATURE', 'MODULE_KEY'],
            [$namespace, $className, $signature, $module->key],
            <<<'PHP'
<?php

namespace NAMESPACE\Console\Commands;

use Illuminate\Console\Command;

class CLASS extends Command
{
    protected $signature = 'SIGNATURE';

    protected $description = 'Module command for MODULE_KEY';

    public function handle(): int
    {
        $this->components->info('Command executed for module MODULE_KEY.');

        return self::SUCCESS;
    }
}

PHP
        );

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makeRequest(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }

        $namespace = $this->namespaceFor($module);
        $absolute = $module->path
            .DIRECTORY_SEPARATOR
            .'Http'
            .DIRECTORY_SEPARATOR
            .'Requests'
            .DIRECTORY_SEPARATOR
            .$className
            .'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $contents = str_replace(
            ['NAMESPACE', 'CLASS'],
            [$namespace, $className],
            <<<'PHP'
<?php

namespace NAMESPACE\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CLASS extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}

PHP
        );

        return ['path' => $this->write($absolute, $contents)];
    }

    /**
     * @return array{path: string, extras?: array<string, string>}
     */
    public function makePolicy(ModuleManifest $module, string $name): array
    {
        $className = Str::studly($name);
        if (! str_ends_with($className, 'Policy')) {
            $className .= 'Policy';
        }

        $modelClass = Str::studly(str_replace('Policy', '', $className));
        $namespace = $this->namespaceFor($module);
        $absolute = $module->path.DIRECTORY_SEPARATOR.'Policies'.DIRECTORY_SEPARATOR.$className.'.php';

        if (is_file($absolute)) {
            throw ModuleScaffoldException::fileExists($absolute);
        }

        $permission = 'module.'.$module->key.'.'.Str::snake(Str::pluralStudly($modelClass)).'.manage';

        $contents = str_replace(
            ['NAMESPACE', 'CLASS', 'MODEL', 'PERMISSION'],
            [$namespace, $className, $modelClass, $permission],
            <<<'PHP'
<?php

namespace NAMESPACE\Policies;

use NAMESPACE\Models\MODEL;

class CLASS
{
    public function viewAny(?object $user): bool
    {
        return $user?->can('PERMISSION') ?? false;
    }

    public function view(?object $user, MODEL $model): bool
    {
        return $user?->can('PERMISSION') ?? false;
    }
}

PHP
        );

        $manifestUpdated = ModuleManifestWriter::addPermission($module->path, [
            'name' => $permission,
            'description' => 'Manage '.$modelClass.' records for this module.',
        ]);

        $result = ['path' => $this->write($absolute, $contents)];

        if ($manifestUpdated) {
            $result['extras']['Manifest'] = 'permissions[] updated';
        }

        $result['extras']['Permission'] = $permission;

        return $result;
    }

    private function namespaceFor(ModuleManifest $module): string
    {
        $directory = basename($module->path);

        return 'Modules\\'.$directory;
    }

    private function resolveEventClass(ModuleManifest $module, ?string $event): ?string
    {
        if (! is_string($event) || trim($event) === '') {
            return null;
        }

        $event = trim($event);

        if (str_contains($event, '\\')) {
            return ltrim($event, '\\');
        }

        return $this->namespaceFor($module).'\\Events\\'.Str::studly($event);
    }

    private function appendControllerRoute(
        ModuleManifest $module,
        string $namespace,
        string $controllerClass,
        string $routeScope,
    ): string {
        $routeScope = strtolower(trim($routeScope));

        if (! in_array($routeScope, ['admin', 'client', 'api', 'web'], true)) {
            throw new InvalidArgumentException('Route scope must be admin, client, api, or web.');
        }

        $routeFile = $routeScope === 'web'
            ? 'web.php'
            : $routeScope.'.php';
        $absolute = $module->path.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.$routeFile;

        if (! is_file($absolute)) {
            $stub = $routeScope === 'api'
                ? "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n"
                : "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";
            $this->write($absolute, $stub);
        }

        $uri = '/'.$module->key.'/'.Str::kebab(str_replace('Controller', '', $controllerClass));
        $routeName = 'module.'.$module->key.'.'.Str::kebab(str_replace('Controller', '', $controllerClass));
        $import = "use {$namespace}\\Http\\Controllers\\{$controllerClass};";
        $routeLine = "Route::get('{$uri}', {$controllerClass}::class)->name('{$routeName}');";

        $contents = (string) file_get_contents($absolute);

        if (! str_contains($contents, $import)) {
            $contents = preg_replace(
                '/(<\?php\s*\n)/',
                "$1{$import}\n",
                $contents,
                1,
            ) ?? $contents;
        }

        if (! str_contains($contents, $routeLine)) {
            $contents = rtrim($contents).PHP_EOL.$routeLine.PHP_EOL;
        }

        file_put_contents($absolute, $contents);

        return $absolute;
    }

    private function migrationTableSuffix(string $name): string
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        $name = preg_replace('/\.php$/', '', $name) ?? $name;
        $name = preg_replace('/^create_/', '', $name) ?? $name;
        $name = preg_replace('/_table$/', '', $name) ?? $name;
        $name = Str::snake(str_replace('/', '_', $name));

        if ($name === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw ModuleScaffoldException::invalidMigrationName($name);
        }

        return $name;
    }

    private function write(string $path, string $contents): string
    {
        File::ensureDirectoryExists(dirname($path));

        if (File::put($path, $contents) === false) {
            throw ModuleScaffoldException::writeFailed($path);
        }

        return $path;
    }
}
