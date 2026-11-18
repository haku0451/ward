<?php

namespace App\Actions;

use App\Enums\ApplyStatus;
use App\Enums\IpBlockSource;
use App\Enums\WafMode;
use App\Models\IpBlock;
use App\Models\Node;
use App\Models\Site;
use App\Models\WafSetting;
use Illuminate\Support\Carbon;

class RestorePolicyState
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $this->restoreSettings($payload);
        $this->restoreModes($payload);

        $wanted = is_array($payload['ip_blocks'] ?? null) ? $payload['ip_blocks'] : [];
        $wantedKeys = [];

        foreach ($wanted as $item) {
            if (! is_array($item) || ! isset($item['cidr'])) {
                continue;
            }

            $wantedKeys[] = $this->key(
                (string) $item['cidr'],
                isset($item['node_id']) ? (int) $item['node_id'] : null,
                isset($item['site_id']) ? (int) $item['site_id'] : null,
            );
        }

        foreach (IpBlock::query()->active()->get() as $block) {
            if (! in_array($this->key($block->cidr, $block->node_id, $block->site_id), $wantedKeys, true)) {
                $block->forceFill([
                    'revoked_at' => now(),
                    'revoke_reason' => 'policy rollback',
                ])->save();
            }
        }

        foreach ($wanted as $item) {
            if (! is_array($item) || ! isset($item['cidr'])) {
                continue;
            }

            $cidr = (string) $item['cidr'];
            $nodeId = isset($item['node_id']) ? (int) $item['node_id'] : null;
            $siteId = isset($item['site_id']) ? (int) $item['site_id'] : null;
            $expires = isset($item['expires_at']) ? Carbon::parse((string) $item['expires_at']) : null;

            $existing = IpBlock::query()
                ->where('cidr', $cidr)
                ->when(
                    $nodeId === null,
                    fn ($query) => $query->whereNull('node_id'),
                    fn ($query) => $query->where('node_id', $nodeId),
                )
                ->when(
                    $siteId === null,
                    fn ($query) => $query->whereNull('site_id'),
                    fn ($query) => $query->where('site_id', $siteId),
                )
                ->latest('id')
                ->first();

            if ($existing !== null) {
                $existing->forceFill([
                    'revoked_at' => null,
                    'revoked_by' => null,
                    'revoke_reason' => null,
                    'expires_at' => $expires,
                    'apply_status' => ApplyStatus::Pending,
                ])->save();

                continue;
            }

            IpBlock::query()->create([
                'cidr' => $cidr,
                'source' => IpBlockSource::tryFrom((string) ($item['source'] ?? '')) ?? IpBlockSource::Manual,
                'reason' => 'Restored by policy rollback',
                'node_id' => $nodeId,
                'site_id' => $siteId,
                'expires_at' => $expires,
                'apply_status' => ApplyStatus::Pending,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function restoreSettings(array $payload): void
    {
        $settings = WafSetting::current();

        if (isset($payload['waf_mode']) && is_string($payload['waf_mode'])) {
            $mode = WafMode::tryFrom($payload['waf_mode']);
            if ($mode !== null) {
                $settings->waf_mode = $mode;
            }
        }

        $auto = is_array($payload['auto_block'] ?? null) ? $payload['auto_block'] : [];

        if ($auto !== []) {
            $settings->auto_block_enabled = (bool) ($auto['enabled'] ?? false);
            $settings->auto_block_threshold = (int) ($auto['threshold'] ?? $settings->auto_block_threshold);
            $settings->auto_block_window_seconds = (int) ($auto['window_seconds'] ?? $settings->auto_block_window_seconds);
            $settings->auto_block_duration_seconds = (int) ($auto['duration_seconds'] ?? $settings->auto_block_duration_seconds);
        }

        $settings->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function restoreModes(array $payload): void
    {
        foreach (is_array($payload['nodes'] ?? null) ? $payload['nodes'] : [] as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }

            $node = Node::query()->find($item['id']);
            if ($node === null) {
                continue;
            }

            $node->waf_mode = isset($item['waf_mode']) && is_string($item['waf_mode'])
                ? WafMode::tryFrom($item['waf_mode'])
                : null;
            $node->save();
        }

        foreach (is_array($payload['sites'] ?? null) ? $payload['sites'] : [] as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }

            $site = Site::query()->find($item['id']);
            if ($site === null) {
                continue;
            }

            $site->waf_mode = isset($item['waf_mode']) && is_string($item['waf_mode'])
                ? WafMode::tryFrom($item['waf_mode'])
                : null;
            $site->save();
        }
    }

    private function key(string $cidr, ?int $nodeId, ?int $siteId): string
    {
        return $cidr.'|'.($nodeId ?? 'g').'|'.($siteId ?? 'g');
    }
}
