<?php

namespace Core\Sync\Enums;

enum ServiceSyncDivergenceType: string
{
    case ExternalDeleted = 'external_deleted';
    case StatusMismatch = 'status_mismatch';
    case IpChanged = 'ip_changed';
    case HostnameChanged = 'hostname_changed';
    case ExternalIdChanged = 'external_id_changed';
}
