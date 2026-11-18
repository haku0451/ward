<?php

namespace App\Models;

use App\Enums\EventAction;
use Database\Factories\AttackEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_id
 * @property int $node_id
 * @property int|null $site_id
 * @property string|null $hostname
 * @property string $client_ip
 * @property string $method
 * @property string $path
 * @property string|null $rule_id
 * @property string|null $rule_message
 * @property EventAction $action
 * @property int|null $http_status
 * @property string|null $correlation_id
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'event_id',
    'node_id',
    'site_id',
    'hostname',
    'client_ip',
    'method',
    'path',
    'rule_id',
    'rule_message',
    'action',
    'http_status',
    'correlation_id',
    'occurred_at',
])]
class AttackEvent extends Model
{
    /** @use HasFactory<AttackEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => EventAction::class,
            'occurred_at' => 'datetime',
            'http_status' => 'integer',
        ];
    }
}
