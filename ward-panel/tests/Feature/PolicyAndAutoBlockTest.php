<?php

use App\Enums\EventAction;
use App\Models\IpBlock;
use App\Models\Node;
use App\Models\User;
use App\Models\WafSetting;
use Illuminate\Support\Str;

test('agent config includes a signature', function () {
    $plain = Node::generatePlainToken();
    Node::factory()->create([
        'token_prefix' => Node::tokenPrefix($plain),
        'token_hash' => Node::hashToken($plain),
    ]);

    $this->getJson('/api/agent/v1/config', agentHeaders($plain))
        ->assertOk()
        ->assertJsonStructure(['version', 'hash', 'signature', 'public_key', 'payload']);
});

test('auto block stays disabled by default', function () {
    expect(WafSetting::current()->auto_block_enabled)->toBeFalse();
});

test('auto block is not created while disabled', function () {
    $plain = Node::generatePlainToken();
    $node = Node::factory()->create([
        'token_prefix' => Node::tokenPrefix($plain),
        'token_hash' => Node::hashToken($plain),
    ]);

    $this->postJson('/api/agent/v1/events', [
        'events' => [[
            'event_id' => (string) Str::uuid(),
            'occurred_at' => now()->toIso8601String(),
            'client_ip' => '203.0.113.77',
            'method' => 'GET',
            'path' => '/',
            'action' => EventAction::Detect->value,
        ]],
    ], agentHeaders($plain))->assertOk();

    expect(IpBlock::query()->where('cidr', '203.0.113.77/32')->exists())->toBeFalse();
    expect($node->id)->toBeInt();
});

test('admins can roll a policy back by publishing a new version', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('blocks.store'), ['cidr' => '203.0.113.8']);
    $first = \App\Models\PolicyRevision::query()->latest('id')->first();
    $this->actingAs($admin)->post(route('blocks.store'), ['cidr' => '203.0.113.9']);

    $this->actingAs($admin)
        ->post(route('policies.rollback', $first))
        ->assertRedirect(route('policies.index'));

    $latest = \App\Models\PolicyRevision::query()->latest('id')->first();
    expect($latest->rolled_back_from_id)->toBe($first->id)
        ->and($latest->id)->toBeGreaterThan($first->id)
        ->and(IpBlock::query()->active()->where('cidr', '203.0.113.8/32')->exists())->toBeTrue()
        ->and(IpBlock::query()->active()->where('cidr', '203.0.113.9/32')->exists())->toBeFalse();

    $plain = Node::generatePlainToken();
    Node::factory()->create([
        'token_prefix' => Node::tokenPrefix($plain),
        'token_hash' => Node::hashToken($plain),
    ]);

    $this->getJson('/api/agent/v1/config', agentHeaders($plain))
        ->assertOk()
        ->assertJsonPath('version', $latest->id);

    expect(\App\Models\PolicyRevision::query()->latest('id')->first()->id)->toBe($latest->id);
});

test('auto block creates a timed ban when enabled', function () {
    $settings = WafSetting::current();
    $settings->forceFill([
        'auto_block_enabled' => true,
        'auto_block_threshold' => 1,
        'auto_block_window_seconds' => 300,
        'auto_block_duration_seconds' => 3600,
    ])->save();

    $plain = Node::generatePlainToken();
    Node::factory()->create([
        'token_prefix' => Node::tokenPrefix($plain),
        'token_hash' => Node::hashToken($plain),
    ]);

    $this->postJson('/api/agent/v1/events', [
        'events' => [[
            'event_id' => (string) Str::uuid(),
            'occurred_at' => now()->toIso8601String(),
            'client_ip' => '203.0.113.88',
            'method' => 'GET',
            'path' => '/',
            'action' => EventAction::Detect->value,
        ]],
    ], agentHeaders($plain))->assertOk();

    $block = IpBlock::query()->where('cidr', '203.0.113.88/32')->first();

    expect($block)->not->toBeNull()
        ->and($block->source->value)->toBe('automatic')
        ->and($block->expires_at)->not->toBeNull();
});

test('enabling auto block requires confirmation', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('policies.update'), [
            'waf_mode' => 'detection_only',
            'auto_block_enabled' => true,
            'auto_block_threshold' => 20,
            'auto_block_window_seconds' => 300,
            'auto_block_duration_seconds' => 3600,
        ])
        ->assertSessionHasErrors('confirm_auto_block');
});
