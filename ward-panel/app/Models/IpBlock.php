<?php

namespace App\Models;

use App\Enums\ApplyStatus;
use App\Enums\IpBlockSource;
use Database\Factories\IpBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $cidr
 * @property IpBlockSource $source
 * @property string|null $reason
 * @property int|null $created_by
 * @property int|null $node_id
 * @property int|null $site_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property string|null $revoke_reason
 * @property ApplyStatus $apply_status
 */
#[Fillable([
    'cidr',
    'source',
    'reason',
    'created_by',
    'node_id',
    'site_id',
    'expires_at',
    'revoked_at',
    'revoked_by',
    'revoke_reason',
    'apply_status',
])]
class IpBlock extends Model
{
    /** @use HasFactory<IpBlockFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

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
     * @return HasMany<IpBlockResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(IpBlockResult::class);
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<IpBlock>  $query
     * @return Builder<IpBlock>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => IpBlockSource::class,
            'apply_status' => ApplyStatus::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
