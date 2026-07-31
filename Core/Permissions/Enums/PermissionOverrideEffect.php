<?php

namespace Core\Permissions\Enums;

enum PermissionOverrideEffect: string
{
    case Grant = 'grant';
    case Deny = 'deny';
}
