<?php

namespace Core\Sync\Enums;

enum ServiceSyncResolutionAction: string
{
    case TerminatedLocally = 'terminated_locally';
    case StatusSynced = 'status_synced';
    case IpSynced = 'ip_synced';
    case HostnameSynced = 'hostname_synced';
    case ExternalIdSynced = 'external_id_synced';
}
