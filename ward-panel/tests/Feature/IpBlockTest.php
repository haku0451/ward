<?php

use App\Models\IpBlock;
use App\Models\IpProtection;
use App\Models\User;

test('admins can create a manual ip block', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('blocks.store'), [
            'cidr' => '203.0.113.10',
            'reason' => 'scanner',
        ])
        ->assertRedirect(route('blocks.index'));

    $this->assertDatabaseHas('ip_blocks', [
        'cidr' => '203.0.113.10/32',
        'source' => 'manual',
    ]);
});

test('protected addresses cannot be blocked', function () {
    $admin = User::factory()->admin()->create();
    IpProtection::factory()->create(['cidr' => '198.51.100.0/24']);

    $this->actingAs($admin)
        ->post(route('blocks.store'), [
            'cidr' => '198.51.100.10',
        ])
        ->assertSessionHasErrors('cidr');
});

test('viewers cannot create blocks', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->post(route('blocks.store'), [
            'cidr' => '203.0.113.10',
        ])
        ->assertForbidden();
});

test('admins can set a node specific waf mode', function () {
    $admin = User::factory()->admin()->create();
    $node = \App\Models\Node::factory()->create();

    $this->actingAs($admin)
        ->patch(route('nodes.update', $node), ['waf_mode' => 'blocking'])
        ->assertRedirect(route('nodes.index'));

    expect($node->fresh()->waf_mode->value)->toBe('blocking');
});

test('admins can revoke an active block', function () {
    $admin = User::factory()->admin()->create();
    $block = IpBlock::factory()->create();

    $this->actingAs($admin)
        ->post(route('blocks.revoke', $block))
        ->assertRedirect(route('blocks.index'));

    expect($block->fresh()->revoked_at)->not->toBeNull();
});
