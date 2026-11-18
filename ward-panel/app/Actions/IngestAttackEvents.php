<?php

namespace App\Actions;

use App\Enums\EventAction;
use App\Models\AttackEvent;
use App\Models\Node;
use App\Models\Site;
use App\Support\PathNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IngestAttackEvents
{
    public function __construct(private EvaluateAutoBlock $autoBlock) {}

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{accepted: int, duplicates: int}
     */
    public function handle(Node $node, array $events): array
    {
        $maskKeys = config('ward.mask_query_keys');
        $maskKeys = is_array($maskKeys) ? array_values(array_map(strval(...), $maskKeys)) : [];

        $accepted = 0;
        $duplicates = 0;

        $hostnames = [];

        foreach ($events as $event) {
            $hostname = isset($event['hostname']) ? (string) $event['hostname'] : null;

            if ($hostname !== null && $hostname !== '') {
                $hostnames[$hostname] = true;
            }
        }

        $sites = Site::query()
            ->whereIn('hostname', array_keys($hostnames))
            ->get()
            ->keyBy('hostname');

        DB::transaction(function () use ($node, $events, $maskKeys, $sites, &$accepted, &$duplicates): void {
            foreach ($events as $event) {
                $eventId = (string) $event['event_id'];

                $exists = AttackEvent::query()
                    ->where('node_id', $node->id)
                    ->where('event_id', $eventId)
                    ->exists();

                if ($exists) {
                    $duplicates++;

                    continue;
                }

                $hostname = isset($event['hostname']) ? (string) $event['hostname'] : null;
                $site = $hostname !== null ? $sites->get($hostname) : null;

                AttackEvent::query()->create([
                    'event_id' => $eventId,
                    'node_id' => $node->id,
                    'site_id' => $site?->id,
                    'hostname' => $hostname,
                    'client_ip' => (string) $event['client_ip'],
                    'method' => mb_strtoupper((string) $event['method']),
                    'path' => PathNormalizer::normalize((string) $event['path'], $maskKeys),
                    'rule_id' => isset($event['rule_id']) ? mb_substr((string) $event['rule_id'], 0, 64) : null,
                    'rule_message' => isset($event['rule_message']) ? mb_substr((string) $event['rule_message'], 0, 1024) : null,
                    'action' => EventAction::from((string) $event['action']),
                    'http_status' => isset($event['http_status']) ? (int) $event['http_status'] : null,
                    'correlation_id' => isset($event['correlation_id']) ? mb_substr((string) $event['correlation_id'], 0, 64) : null,
                    'occurred_at' => Carbon::parse((string) $event['occurred_at']),
                ]);

                $accepted++;
            }
        });

        if ($accepted > 0) {
            $ips = [];

            foreach ($events as $event) {
                if (isset($event['client_ip'])) {
                    $ips[] = (string) $event['client_ip'];
                }
            }

            $this->autoBlock->handle($ips);
        }

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
        ];
    }
}
