<?php

namespace App\Models;

use App\Enums\WafMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $hash
 * @property array<string, mixed> $payload
 * @property string $crs_version
 * @property string $crs_source
 * @property WafMode $waf_mode
 * @property string|null $signature
 * @property string|null $public_key
 * @property int|null $rolled_back_from_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'hash',
    'payload',
    'crs_version',
    'crs_source',
    'waf_mode',
    'signature',
    'public_key',
    'rolled_back_from_id',
])]
class PolicyRevision extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'waf_mode' => WafMode::class,
        ];
    }
}
