<?php

namespace App\Enums;

enum EventAction: string
{
    case Detect = 'detect';
    case Block = 'block';
    case Exception = 'exception';
    case Error = 'error';
}
