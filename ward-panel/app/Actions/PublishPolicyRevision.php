<?php

namespace App\Actions;

use App\Enums\ApplyStatus;
use App\Enums\WafMode;
use App\Models\IpBlock;
use App\Models\IpBlockResult;
use App\Models\IpProtection;
use App\Models\Node;
use App\Models\PolicyRevision;
use App\Models\Site;
use App\Models\WafSetting;
use App\Support\PolicySigner;
use Illuminate\Support\Facades\DB;

class PublishPolicyRevision
{
    public function __construct(private PolicySigner $signer) {}

    /**
     * @param  array<string, mixed>|null  $payloadOverride
     */
    public function handle(?int $rolledBackFromId = null, ?array $payloadOverride = null): PolicyRevision
    {
        $settings = WafSetting::current();
        $mode = $settings->waf_mode;
        $crsVersion = (string) config('ward.crs_version');
        $crsSource = (string) config('ward.crs_source');

        if ($payloadOverride !== null) {
            $payload = $payloadOverride;
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $blocks = is_array($payload['ip_blocks'] ?? null) ? $payload['ip_blocks'] : [];

            return $this->persist($payload, $hash, $crsVersion, $crsSource, $mode, $rolledBackFromId, $blocks);
        }

        $sites = Site::query()
            ->with('node:id,waf_mode')
            ->orderBy('id')
            ->get()
            ->map(function (Site $site) use ($mode): array {
                $resolved = $site->waf_mode
                    ?? $site->node?->waf_mode
                    ?? $mode;

                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'hostname' => $site->hostname,
                    'origin_url' => $site->origin_url,
                    'waf_mode' => $resolved->value,
                    'node_id' => $site->node_id,
                ];
            })
            ->all();

        $nodes = Node::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Node $node): array => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'waf_mode' => ($node->waf_mode ?? $mode)->value,
            ])
            ->all();

        $blocks = IpBlock::query()
            ->active()
            ->with('site:id,hostname')
            ->orderBy('id')
            ->get()
            ->map(fn (IpBlock $block): array => [
                'id' => $block->id,
                'cidr' => $block->cidr,
                'source' => $block->source->value,
                'node_id' => $block->node_id,
                'site_id' => $block->site_id,
                'hostname' => $block->site?->hostname,
                'expires_at' => $block->expires_at?->toIso8601String(),
            ])
            ->all();

        $protections = IpProtection::query()
            ->orderBy('id')
            ->get()
            ->map(fn (IpProtection $protection): array => [
                'id' => $protection->id,
                'cidr' => $protection->cidr,
            ])
            ->all();

        $payload = [
            'waf_mode' => $mode->value,
            'crs_version' => $crsVersion,
            'crs_source' => $crsSource,
            'policy_priority' => ['site', 'node', 'global'],
            'auto_block' => [
                'enabled' => $settings->auto_block_enabled,
                'threshold' => $settings->auto_block_threshold,
                'window_seconds' => $settings->auto_block_window_seconds,
                'duration_seconds' => $settings->auto_block_duration_seconds,
            ],
            'nodes' => $nodes,
            'sites' => $sites,
            'ip_blocks' => $blocks,
            'ip_protections' => $protections,
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $latest = PolicyRevision::query()->latest('id')->first();

        if ($latest !== null && $latest->hash === $hash && $rolledBackFromId === null) {
            return $latest;
        }

        return $this->persist($payload, $hash, $crsVersion, $crsSource, $mode, $rolledBackFromId, $blocks);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $blocks
     */
    private function persist(
        array $payload,
        string $hash,
        string $crsVersion,
        string $crsSource,
        WafMode $mode,
        ?int $rolledBackFromId,
        array $blocks,
    ): PolicyRevision {
        return DB::transaction(function () use ($payload, $hash, $crsVersion, $crsSource, $mode, $rolledBackFromId, $blocks): PolicyRevision {
            $revision = new PolicyRevision;
            $revision->hash = $hash;
            $revision->payload = $payload;
            $revision->crs_version = $crsVersion;
            $revision->crs_source = $crsSource;
            $revision->waf_mode = $mode;
            $revision->rolled_back_from_id = $rolledBackFromId;
            $revision->save();

            $revision->public_key = $this->signer->publicKey();
            $revision->signature = $this->signer->sign(PolicySigner::signingMessage($revision->id, $hash));
            $revision->save();

            foreach ($blocks as $block) {
                if (! isset($block['id'])) {
                    continue;
                }

                $nodeIds = ($block['node_id'] ?? null) === null
                    ? Node::query()->pluck('id')
                    : collect([(int) $block['node_id']]);

                foreach ($nodeIds as $nodeId) {
                    IpBlockResult::query()->updateOrCreate(
                        [
                            'ip_block_id' => $block['id'],
                            'node_id' => $nodeId,
                        ],
                        [
                            'status' => ApplyStatus::Pending,
                            'config_version' => $revision->id,
                            'message' => null,
                        ],
                    );
                }
            }

            return $revision;
        });
    }
}
