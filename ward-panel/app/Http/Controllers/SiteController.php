<?php

namespace App\Http\Controllers;

use App\Actions\PublishPolicyRevision;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(): Response
    {
        $sites = Site::query()
            ->with('node:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'hostname' => $site->hostname,
                'origin_url' => $site->origin_url,
                'waf_mode' => $site->waf_mode?->value,
                'node' => $site->node?->name,
            ]);

        return Inertia::render('sites/index', [
            'sites' => $sites,
            'nodes' => Node::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreSiteRequest $request, PublishPolicyRevision $publish): RedirectResponse
    {
        $site = Site::query()->create($request->safe()->only([
            'name',
            'hostname',
            'origin_url',
            'waf_mode',
            'node_id',
        ]));

        $publish->handle();

        AuditLog::record(
            'site.created',
            $site,
            [
                'hostname' => $site->hostname,
                'origin_url' => $site->origin_url,
            ],
            $request->user(),
            $request,
        );

        return redirect()->route('sites.index')->with('status', '保護対象サイトを登録しました。');
    }

    public function update(UpdateSiteRequest $request, Site $site, PublishPolicyRevision $publish): RedirectResponse
    {
        $site->waf_mode = $request->input('waf_mode');
        $site->save();
        $publish->handle();

        AuditLog::record(
            'site.policy_updated',
            $site,
            ['waf_mode' => $site->waf_mode?->value],
            $request->user(),
            $request,
        );

        return redirect()->route('sites.index')->with('status', 'サイト別ポリシーを更新しました。');
    }
}
