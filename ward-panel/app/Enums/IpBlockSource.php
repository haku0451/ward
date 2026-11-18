<?php

namespace App\Enums;

enum IpBlockSource: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
