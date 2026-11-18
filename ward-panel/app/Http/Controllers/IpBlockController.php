<?php

namespace App\Http\Controllers;

use App\Actions\PublishPolicyRevision;
use App\Enums\ApplyStatus;
use App\Enums\IpBlockSource;
use App\Http\Requests\StoreIpBlockRequest;
use App\Models\AuditLog;
use App\Models\IpBlock;
use App\Models\Node;
use App\Models\Site;
use App\Support\Cidr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IpBlockController extends Controller
{
    public function index(): Response
    {
        $blocks = IpBlock::query()
            ->with(['node:id,name', 'site:id,name,hostname', 'results.node:id,name'])
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (IpBlock $block): array => [
                'id' => $block->id,
                'cidr' => $block->cidr,
                'source' => $block->source->value,
                'reason' => $block->reason,
                'node' => $block->node?->name,
                'site' => $block->site?->hostname,
                'expires_at' => $block->expires_at?->toIso8601String(),
                'revoked_at' => $block->revoked_at?->toIso8601String(),
                'apply_status' => $block->apply_status->value,
                'results' => $block->results->map(fn ($result): array => [
                    'node' => $result->node?->name,
                    'status' => $result->status->value,
                    'message' => $result->message,
                ]),
                'active' => $block->isActive(),
            ]);

        return Inertia::render('blocks/index', [
            'blocks' => $blocks,
            'nodes' => Node::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'hostname']),
        ]);
    }

    public function store(StoreIpBlockRequest $request, PublishPolicyRevision $publish): RedirectResponse
    {
        $block = IpBlock::query()->create([
            'cidr' => Cidr::normalize($request->string('cidr')->toString()),
            'source' => IpBlockSource::Manual,
            'reason' => $request->input('reason'),
            'created_by' => $request->user()?->id,
            'node_id' => $request->input('node_id'),
            'site_id' => $request->input('site_id'),
            'expires_at' => $request->input('expires_at'),
            'apply_status' => ApplyStatus::Pending,
        ]);

        $publish->handle();

        AuditLog::record('ip_block.created', $block, [
            'cidr' => $block->cidr,
            'source' => $block->source->value,
        ], $request->user(), $request);

        return redirect()->route('blocks.index')->with('status', '遮断ルールを登録しました。');
    }

    public function destroy(Request $request, IpBlock $block, PublishPolicyRevision $publish): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->validate([
            'revoke_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $block->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $request->user()?->id,
            'revoke_reason' => $request->input('revoke_reason'),
        ])->save();

        $publish->handle();

        AuditLog::record('ip_block.revoked', $block, [
            'cidr' => $block->cidr,
        ], $request->user(), $request);

        return redirect()->route('blocks.index')->with('status', '遮断を解除しました。');
    }
}
