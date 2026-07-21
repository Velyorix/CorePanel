<?php

namespace Core\Sync\Enums;

enum SyncLogSubject: string
{
    case Service = 'service';
    case Node = 'node';
}
