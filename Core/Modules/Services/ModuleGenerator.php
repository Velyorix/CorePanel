<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleScaffoldException;
use Core\Modules\Support\ModuleName;
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

    public function makeMigration(ModuleManifest $module, string $name): string
    {
        $tableSuffix = $this->migrationTableSuffix($name);
        $table = 'module_'.$module->key.'_'.$tableSuffix;
        $className = Str::studly('create_'.$table.'_table');
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

        return $this->write($absolute, $contents);
    }

    public function makeModel(ModuleManifest $module, string $name): string
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

        return $this->write($absolute, $contents);
    }

    public function makeController(ModuleManifest $module, string $name): string
    {
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

        return $this->write($absolute, $contents);
    }

    public function makeService(ModuleManifest $module, string $name): string
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

        return $this->write($absolute, $contents);
    }

    public function makeEvent(ModuleManifest $module, string $name): string
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

        return $this->write($absolute, $contents);
    }

    private function namespaceFor(ModuleManifest $module): string
    {
        $directory = basename($module->path);

        return 'Modules\\'.$directory;
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
