<?php

namespace App\Models;

use App\Enums\ApplyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ip_block_id
 * @property int $node_id
 * @property ApplyStatus $status
 * @property string|null $message
 * @property int|null $config_version
 */
#[Fillable(['ip_block_id', 'node_id', 'status', 'message', 'config_version', 'reported_at'])]
class IpBlockResult extends Model
{
    /**
     * @return BelongsTo<IpBlock, $this>
     */
    public function ipBlock(): BelongsTo
    {
        return $this->belongsTo(IpBlock::class);
    }

    /**
     * @return BelongsTo<Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplyStatus::class,
            'reported_at' => 'datetime',
            'config_version' => 'integer',
        ];
    }
}
