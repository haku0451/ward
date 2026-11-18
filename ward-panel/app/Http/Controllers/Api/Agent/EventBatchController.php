<?php

namespace App\Http\Controllers\Api\Agent;

use App\Actions\IngestAttackEvents;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentEventBatchRequest;
use App\Models\Node;
use Illuminate\Http\JsonResponse;

class EventBatchController extends Controller
{
    public function store(StoreAgentEventBatchRequest $request, IngestAttackEvents $ingest): JsonResponse
    {
        /** @var Node $node */
        $node = $request->attributes->get('agentNode');

        $result = $ingest->handle($node, $request->validated('events'));

        $node->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'status' => 'ok',
            ...$result,
        ]);
    }
}
