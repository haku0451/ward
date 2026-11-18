<?php

namespace App\Enums;

enum ApplyStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
