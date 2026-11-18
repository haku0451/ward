<?php

namespace App\Models;

use App\Enums\WafMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property WafMode $waf_mode
 * @property bool $auto_block_enabled
 * @property int $auto_block_threshold
 * @property int $auto_block_window_seconds
 * @property int $auto_block_duration_seconds
 */
#[Fillable([
    'waf_mode',
    'auto_block_enabled',
    'auto_block_threshold',
    'auto_block_window_seconds',
    'auto_block_duration_seconds',
])]
class WafSetting extends Model
{
    public static function current(): self
    {
        $settings = self::query()->first();

        if ($settings !== null) {
            return $settings;
        }

        return self::query()->create([
            'waf_mode' => WafMode::DetectionOnly,
            'auto_block_enabled' => false,
            'auto_block_threshold' => 20,
            'auto_block_window_seconds' => 300,
            'auto_block_duration_seconds' => 3600,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waf_mode' => WafMode::class,
            'auto_block_enabled' => 'boolean',
            'auto_block_threshold' => 'integer',
            'auto_block_window_seconds' => 'integer',
            'auto_block_duration_seconds' => 'integer',
        ];
    }
}
