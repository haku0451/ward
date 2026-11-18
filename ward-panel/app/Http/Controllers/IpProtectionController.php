<?php

namespace App\Http\Controllers;

use App\Actions\PublishPolicyRevision;
use App\Http\Requests\StoreIpProtectionRequest;
use App\Models\AuditLog;
use App\Models\IpProtection;
use App\Support\Cidr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IpProtectionController extends Controller
{
    public function index(): Response
    {
        $protections = IpProtection::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (IpProtection $protection): array => [
                'id' => $protection->id,
                'cidr' => $protection->cidr,
                'reason' => $protection->reason,
            ]);

        return Inertia::render('protections/index', [
            'protections' => $protections,
        ]);
    }

    public function store(StoreIpProtectionRequest $request, PublishPolicyRevision $publish): RedirectResponse
    {
        $protection = IpProtection::query()->create([
            'cidr' => Cidr::normalize($request->string('cidr')->toString()),
            'reason' => $request->input('reason'),
            'created_by' => $request->user()?->id,
        ]);

        $publish->handle();

        AuditLog::record('ip_protection.created', $protection, [
            'cidr' => $protection->cidr,
        ], $request->user(), $request);

        return redirect()->route('protections.index')->with('status', '保護リストに追加しました。');
    }

    public function destroy(Request $request, IpProtection $protection, PublishPolicyRevision $publish): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $cidr = $protection->cidr;
        $protection->delete();
        $publish->handle();

        AuditLog::record('ip_protection.deleted', null, [
            'cidr' => $cidr,
        ], $request->user(), $request);

        return redirect()->route('protections.index')->with('status', '保護リストから削除しました。');
    }
}
