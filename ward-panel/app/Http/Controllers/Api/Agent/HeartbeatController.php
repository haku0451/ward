<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentHeartbeatRequest;
use App\Models\Node;
use App\Models\PolicyRevision;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    public function store(AgentHeartbeatRequest $request): JsonResponse
    {
        /** @var Node $node */
        $node = $request->attributes->get('agentNode');

        $node->forceFill([
            'agent_version' => $request->string('version')->toString(),
            'hostname' => $request->input('hostname', $node->hostname),
            'config_version' => $request->integer('config_version') ?: $node->config_version,
            'config_hash' => $request->input('config_hash', $node->config_hash),
            'last_seen_at' => now(),
        ])->save();

        $latest = PolicyRevision::query()->latest('id')->first();

        return response()->json([
            'status' => 'ok',
            'server_time' => now()->toIso8601String(),
            'desired_config_version' => $latest?->id,
            'desired_config_hash' => $latest?->hash,
        ]);
    }
}
