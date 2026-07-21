<?php

namespace Core\Sync\Enums;

enum SyncLogOutcome: string
{
    case Polled = 'polled';
    case Resolved = 'resolved';
    case Diverged = 'diverged';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Synced = 'synced';
}
