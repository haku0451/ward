<?php

namespace App\Http\Controllers;

use App\Enums\EventAction;
use App\Models\AttackEvent;
use App\Models\Node;
use App\Models\Site;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $from = now()->subDay();

        $detected = AttackEvent::query()->where('occurred_at', '>=', $from)->count();
        $blocked = AttackEvent::query()
            ->where('occurred_at', '>=', $from)
            ->where('action', EventAction::Block)
            ->count();

        $nodes = Node::query()->orderBy('name')->get();
        $online = $nodes->filter(fn (Node $node): bool => $node->isOnline())->count();

        return Inertia::render('dashboard', [
            'stats' => [
                'detected' => $detected,
                'blocked' => $blocked,
                'nodes' => $nodes->count(),
                'online' => $online,
            ],
            'series' => $this->hourlySeries($from),
            'attackTypes' => AttackEvent::query()
                ->where('occurred_at', '>=', $from)
                ->select('rule_id', DB::raw('count(*) as total'))
                ->groupBy('rule_id')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn ($row): array => [
                    'rule_id' => $row->rule_id ?: '(none)',
                    'total' => (int) $row->total,
                ]),
            'topIps' => AttackEvent::query()
                ->where('occurred_at', '>=', $from)
                ->select('client_ip', DB::raw('count(*) as total'))
                ->groupBy('client_ip')
                ->orderByDesc('total')
                ->limit(8)
                ->get()
                ->map(fn ($row): array => [
                    'ip' => $row->client_ip,
                    'total' => (int) $row->total,
                ]),
            'sites' => Site::query()
                ->withCount([
                    'attackEvents as events_count' => fn ($query) => $query->where('occurred_at', '>=', $from),
                ])
                ->orderByDesc('events_count')
                ->limit(8)
                ->get()
                ->map(fn (Site $site): array => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'hostname' => $site->hostname,
                    'events_count' => (int) $site->events_count,
                ]),
            'nodes' => $nodes->map(fn (Node $node): array => [
                'id' => $node->id,
                'uuid' => $node->uuid,
                'name' => $node->name,
                'health' => $node->health(),
                'agent_version' => $node->agent_version,
                'config_version' => $node->config_version,
                'last_seen_at' => $node->last_seen_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return list<array{hour: string, total: int}>
     */
    private function hourlySeries(CarbonImmutable $from): array
    {
        $driver = DB::connection()->getDriverName();

        $bucket = match ($driver) {
            'pgsql' => "to_char(date_trunc('hour', occurred_at), 'YYYY-MM-DD HH24:00')",
            default => "strftime('%Y-%m-%d %H:00', occurred_at)",
        };

        $rows = AttackEvent::query()
            ->where('occurred_at', '>=', $from)
            ->selectRaw("{$bucket} as hour, count(*) as total")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get();

        return $rows
            ->map(fn ($row): array => [
                'hour' => (string) $row->hour,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
