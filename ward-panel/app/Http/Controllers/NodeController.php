<?php

namespace App\Http\Controllers;

use App\Actions\GenerateAgentConfig;
use App\Actions\IssueNodeToken;
use App\Actions\PublishPolicyRevision;
use App\Models\AuditLog;
use App\Models\Node;
use App\Http\Requests\StoreNodeRequest;
use App\Http\Requests\UpdateNodeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NodeController extends Controller
{
    public function index(): Response
    {
        $nodes = Node::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Node $node): array => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'name' => $node->name,
                'health' => $node->health(),
                'hostname' => $node->hostname,
                'agent_version' => $node->agent_version,
                'config_version' => $node->config_version,
                'waf_mode' => $node->waf_mode?->value,
                'last_seen_at' => $node->last_seen_at?->toIso8601String(),
            ]);

        return Inertia::render('nodes/index', [
            'nodes' => $nodes,
            'plainToken' => session('plain_token'),
            'agentYaml' => session('agent_yaml'),
        ]);
    }

    public function store(StoreNodeRequest $request, IssueNodeToken $issueToken, PublishPolicyRevision $publish, GenerateAgentConfig $agentConfig): RedirectResponse
    {
        $node = new Node;
        $node->name = $request->string('name')->toString();
        $node->hostname = $request->input('hostname');
        $node->waf_mode = $request->input('waf_mode');
        $plain = $issueToken->handle($node);
        $publish->handle();

        AuditLog::record(
            'node.created',
            $node,
            ['name' => $node->name],
            $request->user(),
            $request,
        );

        return redirect()
            ->route('nodes.index')
            ->with('plain_token', $plain)
            ->with('agent_yaml', $agentConfig->handle($node, $plain))
            ->with('status', 'ノードを作成しました。下の agent.yaml をコピーして起動できます。');
    }

    public function update(UpdateNodeRequest $request, Node $node, PublishPolicyRevision $publish): RedirectResponse
    {
        $node->fill($request->safe()->only(['name', 'hostname', 'waf_mode']));
        $node->save();
        $publish->handle();

        AuditLog::record(
            'node.policy_updated',
            $node,
            ['waf_mode' => $node->waf_mode?->value],
            $request->user(),
            $request,
        );

        return redirect()->route('nodes.index')->with('status', 'ノード別ポリシーを更新しました。');
    }

    public function rotateToken(Request $request, Node $node, IssueNodeToken $issueToken, GenerateAgentConfig $agentConfig): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $plain = $issueToken->handle($node);

        AuditLog::record(
            'node.token_rotated',
            $node,
            ['name' => $node->name],
            $request->user(),
            $request,
        );

        return redirect()
            ->route('nodes.index')
            ->with('plain_token', $plain)
            ->with('agent_yaml', $agentConfig->handle($node, $plain))
            ->with('status', 'トークンを再発行しました。下の agent.yaml をコピーして Agent に貼り付けてください。');
    }

    public function agentConfig(Request $request, Node $node, GenerateAgentConfig $agentConfig): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return redirect()
            ->route('nodes.index')
            ->with('agent_yaml', $agentConfig->handle($node))
            ->with('status', 'トークン以外を埋めた agent.yaml を表示しました。トークン付きが必要なら「トークン再発行」を使ってください。');
    }
}
