<?php

namespace App\Models;

use App\Enums\WafMode;
use Database\Factories\NodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $token_prefix
 * @property string $token_hash
 * @property string|null $hostname
 * @property string|null $agent_version
 * @property int|null $config_version
 * @property string|null $config_hash
 * @property WafMode|null $waf_mode
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'uuid',
    'name',
    'hostname',
    'agent_version',
    'config_version',
    'config_hash',
    'waf_mode',
    'last_seen_at',
])]
#[Hidden(['token_hash', 'token_prefix'])]
class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory;

    /**
     * @return HasMany<AttackEvent, $this>
     */
    public function attackEvents(): HasMany
    {
        return $this->hasMany(AttackEvent::class);
    }

    /**
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function isOnline(): bool
    {
        if ($this->last_seen_at === null) {
            return false;
        }

        $staleAfter = (int) config('ward.heartbeat_stale_after');

        return $this->last_seen_at->gte(now()->subSeconds($staleAfter));
    }

    public function health(): string
    {
        return $this->isOnline() ? 'online' : 'offline';
    }

    public static function generatePlainToken(): string
    {
        return 'ward_'.Str::lower(Str::random(8)).'.'.Str::lower(Str::random(40));
    }

    public static function tokenPrefix(string $plainToken): string
    {
        $parts = explode('.', $plainToken, 2);

        return $parts[0];
    }

    public static function hashToken(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key'));
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        $prefix = self::tokenPrefix($plainToken);

        /** @var self|null $node */
        $node = self::query()->where('token_prefix', $prefix)->first();

        if ($node === null) {
            return null;
        }

        if (! hash_equals($node->token_hash, self::hashToken($plainToken))) {
            return null;
        }

        return $node;
    }

    /**
     * @param  Builder<Node>  $query
     * @return Builder<Node>
     */
    #[Scope]
    protected function online(Builder $query): Builder
    {
        $staleAfter = (int) config('ward.heartbeat_stale_after');

        return $query->where('last_seen_at', '>=', now()->subSeconds($staleAfter));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'config_version' => 'integer',
            'waf_mode' => WafMode::class,
        ];
    }
}
