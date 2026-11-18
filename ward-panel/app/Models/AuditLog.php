<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'action', 'auditable_type', 'auditable_id', 'metadata', 'ip_address'])]
class AuditLog extends Model
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(
        string $action,
        ?Model $auditable = null,
        ?array $metadata = null,
        ?User $user = null,
        ?Request $request = null,
    ): self {
        $log = new self;
        $log->action = $action;
        $log->user_id = $user?->id;
        $log->auditable_type = $auditable !== null ? $auditable::class : null;
        $log->auditable_id = $auditable?->getKey();
        $log->metadata = $metadata;
        $log->ip_address = $request?->ip();
        $log->save();

        return $log;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
