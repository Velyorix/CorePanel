<?php

namespace Core\Services\Services;

use Core\Services\Contracts\ModuleAccessLinkProvider;
use Core\Services\Models\Service;

class NullModuleAccessLinkProvider implements ModuleAccessLinkProvider
{
    public function panelUrl(Service $service): ?string
    {
        return null;
    }

    public function consoleUrl(Service $service): ?string
    {
        return null;
    }
}
