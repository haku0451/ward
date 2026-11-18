<?php

namespace App\Http\Controllers\Api\Agent;

use App\Enums\ApplyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentApplyResultRequest;
use App\Models\IpBlock;
use App\Models\IpBlockResult;
use App\Models\Node;
use Illuminate\Http\JsonResponse;

class ApplyResultController extends Controller
{
    public function store(AgentApplyResultRequest $request): JsonResponse
    {
        /** @var Node $node */
        $node = $request->attributes->get('agentNode');

        $ok = $request->boolean('ok');
        $version = $request->integer('config_version');

        foreach ($request->validated('ip_blocks') ?? [] as $item) {
            IpBlockResult::query()->updateOrCreate(
                [
                    'ip_block_id' => $item['id'],
                    'node_id' => $node->id,
                ],
                [
                    'status' => ApplyStatus::from($item['status']),
                    'message' => $item['message'] ?? null,
                    'config_version' => $version,
                    'reported_at' => now(),
                ],
            );

            $this->refreshBlockStatus((int) $item['id']);
        }

        $node->forceFill([
            'config_version' => $version,
            'config_hash' => $request->string('config_hash')->toString(),
            'last_seen_at' => now(),
        ])->save();

        return response()->json([
            'status' => $ok ? 'ok' : 'reported',
        ]);
    }

    private function refreshBlockStatus(int $blockId): void
    {
        $block = IpBlock::query()->find($blockId);

        if ($block === null) {
            return;
        }

        $results = $block->results()->get();

        if ($results->contains(fn (IpBlockResult $result): bool => $result->status === ApplyStatus::Failed)) {
            $block->apply_status = ApplyStatus::Failed;
        } elseif ($results->isNotEmpty() && $results->every(fn (IpBlockResult $result): bool => $result->status === ApplyStatus::Success)) {
            $block->apply_status = ApplyStatus::Success;
        } else {
            $block->apply_status = ApplyStatus::Pending;
        }

        $block->save();
    }
}
