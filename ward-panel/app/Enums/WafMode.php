<?php

namespace App\Enums;

enum WafMode: string
{
    case DetectionOnly = 'detection_only';
    case Blocking = 'blocking';
}
