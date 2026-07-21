<?php

namespace Core\Modules\DataTransferObjects;

final readonly class ModuleLoadedResources
{
    /**
     * @param  list<string>  $routeFiles
     */
    public function __construct(
        public array $routeFiles = [],
        public bool $views = false,
        public bool $migrations = false,
        public ?string $viewsNamespace = null,
        public ?string $migrationsPath = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->routeFiles === [] && ! $this->views && ! $this->migrations;
    }
}
