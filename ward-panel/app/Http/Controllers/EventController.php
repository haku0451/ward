<?php

namespace App\Http\Controllers;

use App\Enums\EventAction;
use App\Models\AttackEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string'],
            'node_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $events = AttackEvent::query()
            ->with(['node:id,name', 'site:id,name,hostname'])
            ->when(
                isset($validated['action']) && $validated['action'] !== '',
                fn ($query) => $query->where('action', $validated['action']),
            )
            ->when(
                isset($validated['node_id']),
                fn ($query) => $query->where('node_id', $validated['node_id']),
            )
            ->when(
                isset($validated['q']) && $validated['q'] !== '',
                function ($query) use ($validated) {
                    $term = '%'.$validated['q'].'%';

                    $query->where(function ($inner) use ($term): void {
                        $inner->where('client_ip', 'like', $term)
                            ->orWhere('path', 'like', $term)
                            ->orWhere('rule_id', 'like', $term)
                            ->orWhere('hostname', 'like', $term);
                    });
                },
            )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AttackEvent $event): array => [
                'id' => $event->id,
                'event_id' => $event->event_id,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'node' => $event->node?->name,
                'hostname' => $event->hostname,
                'client_ip' => $event->client_ip,
                'method' => $event->method,
                'path' => $event->path,
                'rule_id' => $event->rule_id,
                'rule_message' => $event->rule_message,
                'action' => $event->action->value,
                'http_status' => $event->http_status,
                'correlation_id' => $event->correlation_id,
            ]);

        return Inertia::render('events/index', [
            'events' => $events,
            'filters' => [
                'action' => $validated['action'] ?? '',
                'node_id' => $validated['node_id'] ?? '',
                'q' => $validated['q'] ?? '',
            ],
            'actions' => array_map(fn (EventAction $action): string => $action->value, EventAction::cases()),
        ]);
    }
}
