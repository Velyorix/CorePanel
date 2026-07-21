<?php

namespace Core\Nodes\Enums;

enum NodeLogStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
