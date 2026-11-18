<?php

namespace App\Http\Controllers;

use App\Actions\PublishPolicyRevision;
use App\Actions\RestorePolicyState;
use App\Http\Requests\UpdateWafSettingRequest;
use App\Models\AuditLog;
use App\Models\PolicyRevision;
use App\Models\WafSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PolicyController extends Controller
{
    public function index(): Response
    {
        $settings = WafSetting::current();
        $revisions = PolicyRevision::query()
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (PolicyRevision $revision): array => [
                'id' => $revision->id,
                'hash' => $revision->hash,
                'waf_mode' => $revision->waf_mode->value,
                'signed' => $revision->signature !== null,
                'rolled_back_from_id' => $revision->rolled_back_from_id,
                'created_at' => $revision->created_at?->toIso8601String(),
            ]);

        return Inertia::render('policies/index', [
            'settings' => [
                'waf_mode' => $settings->waf_mode->value,
                'auto_block_enabled' => $settings->auto_block_enabled,
                'auto_block_threshold' => $settings->auto_block_threshold,
                'auto_block_window_seconds' => $settings->auto_block_window_seconds,
                'auto_block_duration_seconds' => $settings->auto_block_duration_seconds,
            ],
            'revisions' => $revisions,
            'priority' => ['サイト', 'ノード', 'グローバル'],
        ]);
    }

    public function update(UpdateWafSettingRequest $request, PublishPolicyRevision $publish): RedirectResponse
    {
        $settings = WafSetting::current();
        $settings->fill($request->safe()->only([
            'waf_mode',
            'auto_block_enabled',
            'auto_block_threshold',
            'auto_block_window_seconds',
            'auto_block_duration_seconds',
        ]));
        $settings->save();

        $publish->handle();

        AuditLog::record('waf_settings.updated', $settings, [
            'waf_mode' => $settings->waf_mode->value,
            'auto_block_enabled' => $settings->auto_block_enabled,
        ], $request->user(), $request);

        return redirect()->route('policies.index')->with('status', 'ポリシーを更新しました。');
    }

    public function rollback(Request $request, PolicyRevision $revision, RestorePolicyState $restore, PublishPolicyRevision $publish): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $latest = PolicyRevision::query()->latest('id')->first();

        if ($latest !== null && $latest->id === $revision->id) {
            return redirect()->route('policies.index')->with('status', 'すでにこの版が適用されています。');
        }

        $restore->handle(is_array($revision->payload) ? $revision->payload : []);
        $restored = $publish->handle($revision->id);

        AuditLog::record('policy.rolled_back', $restored, [
            'from' => $revision->id,
            'to' => $restored->id,
        ], $request->user(), $request);

        return redirect()->route('policies.index')->with('status', '版 '.$revision->id.' の内容で新しい版を発行しました。');
    }
}
