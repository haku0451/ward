<?php

namespace App\Http\Controllers\Api\Agent;

use App\Actions\PublishPolicyRevision;
use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\PolicyRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function show(Request $request, PublishPolicyRevision $publish): JsonResponse
    {
        /** @var Node $node */
        $node = $request->attributes->get('agentNode');

        $revision = PolicyRevision::query()->latest('id')->first() ?? $publish->handle();
        $payload = $revision->payload;

        $sites = [];

        foreach ($payload['sites'] ?? [] as $site) {
            if (! is_array($site)) {
                continue;
            }

            $assigned = $site['node_id'] ?? null;

            if ($assigned !== null && (int) $assigned !== $node->id) {
                continue;
            }

            $sites[] = $site;
        }

        $blocks = [];

        foreach ($payload['ip_blocks'] ?? [] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $assigned = $block['node_id'] ?? null;

            if ($assigned !== null && (int) $assigned !== $node->id) {
                continue;
            }

            $blocks[] = $block;
        }

        $payload['sites'] = $sites;
        $payload['ip_blocks'] = $blocks;

        $nodeMode = null;

        foreach ($payload['nodes'] ?? [] as $item) {
            if (is_array($item) && (int) ($item['id'] ?? 0) === $node->id) {
                $nodeMode = $item['waf_mode'] ?? null;
            }
        }

        return response()->json([
            'version' => $revision->id,
            'hash' => $revision->hash,
            'signature' => $revision->signature,
            'public_key' => $revision->public_key,
            'crs_version' => $revision->crs_version,
            'crs_source' => $revision->crs_source,
            'waf_mode' => $nodeMode ?? $revision->waf_mode->value,
            'node' => [
                'id' => $node->id,
                'uuid' => $node->uuid,
            ],
            'payload' => $payload,
        ]);
    }
}
