<?php

namespace Core\Services\Contracts;

use Core\Services\Models\Service;

/**
 * Module-provided access URLs (SSO / dynamic console). Null stub until providers land.
 */
interface ModuleAccessLinkProvider
{
    public function panelUrl(Service $service): ?string;

    public function consoleUrl(Service $service): ?string;
}
