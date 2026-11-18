<?php

use App\Enums\EventAction;
use App\Models\AttackEvent;
use App\Models\Node;
use App\Models\Site;
use Illuminate\Support\Str;

function agentHeaders(string $token): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

test('agent requests without a token are rejected', function () {
    $this->postJson('/api/agent/v1/heartbeat', [
        'version' => '0.1.0',
    ])->assertUnauthorized();
});

test('a node can send a heartbeat and receive the desired config version', function () {
    $plain = Node::generatePlainToken();
    $node = Node::factory()->create([
        'token_prefix' => Node::tokenPrefix($plain),
        'token_hash' => Node::hashToken($plain),
    ]);

    $this->postJson('/api/agent/v1/heartbeat', [
        'version' => '0.1.0',
        'hostname' => 'waf-1',
        'config_version' => 0,
    ], agentHeaders($plain))
        ->assertOk()
        ->assertJsonPath('status', 'ok');

    expect($node->fresh()->agent_version)->toBe('0.1.0')
        ->and($node->fresh()->last_seen_at)->not->toBeNull();
});

test('a node can ingest events and duplicates are ignored', function () {
    $plain = Node::generatePlainToken();
    $node = Node::factory()->create([
        'token_prefix' => Node::tokenPrefix($plain),
        'token_hash' => Node::hashToken($plain),
    ]);
    Site::factory()->create(['hostname' => 'app.example.test']);
    $eventId = (string) Str::uuid();

    $payload = [
        'events' => [[
            'event_id' => $eventId,
            'occurred_at' => now()->toIso8601String(),
            'hostname' => 'app.example.test',
            'client_ip' => '203.0.113.10',
            'method' => 'GET',
            'path' => '/search?q=1&password=secret',
            'rule_id' => '941100',
            'rule_message' => 'XSS Attack Detected',
            'action' => EventAction::Detect->value,
            'http_status' => 403,
            'correlation_id' => (string) Str::uuid(),
        ]],
    ];

    $this->postJson('/api/agent/v1/events', $payload, agentHeaders($plain))
        ->assertOk()
        ->assertJsonPath('accepted', 1);

    $this->postJson('/api/agent/v1/events', $payload, agentHeaders($plain))
        ->assertOk()
        ->assertJsonPath('duplicates', 1);

    $event = AttackEvent::query()->first();

    expect($event)->not->toBeNull()
        ->and($event->path)->not->toContain('secret')
        ->and($event->path)->toContain('password=%2A%2A%2A');
});

test('agent config only includes assigned or global sites', function () {
    $plain = Node::generatePlainToken();
    $node = Node::factory()->create([
        'token_prefix' => Node::tokenPrefix($plain),
        'token_hash' => Node::hashToken($plain),
    ]);
    $other = Node::factory()->create();

    Site::factory()->create([
        'hostname' => 'global.example.test',
        'node_id' => null,
    ]);
    Site::factory()->create([
        'hostname' => 'other.example.test',
        'node_id' => $other->id,
    ]);

    $this->getJson('/api/agent/v1/config', agentHeaders($plain))
        ->assertOk()
        ->assertJsonPath('payload.sites.0.hostname', 'global.example.test')
        ->assertJsonMissing(['hostname' => 'other.example.test']);
});

test('a node can report ip block apply results', function () {
    $plain = Node::generatePlainToken();
    $node = Node::factory()->create([
        'token_prefix' => Node::tokenPrefix($plain),
        'token_hash' => Node::hashToken($plain),
    ]);
    $block = \App\Models\IpBlock::factory()->create();

    $this->postJson('/api/agent/v1/apply-results', [
        'config_version' => 1,
        'config_hash' => str_repeat('a', 64),
        'ok' => true,
        'ip_blocks' => [[
            'id' => $block->id,
            'status' => 'success',
        ]],
    ], agentHeaders($plain))->assertOk();

    expect($block->fresh()->apply_status->value)->toBe('success')
        ->and($node->fresh()->config_version)->toBe(1);
});
