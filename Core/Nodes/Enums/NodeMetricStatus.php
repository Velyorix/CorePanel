<?php

namespace Core\Nodes\Enums;

enum NodeMetricStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
