<?php

use App\Models\Node;
use App\Models\User;

test('admins can create a node and receive a one-time token', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('nodes.store'), ['name' => 'edge-1'])
        ->assertRedirect(route('nodes.index'))
        ->assertSessionHas('plain_token');

    $this->assertDatabaseHas('nodes', ['name' => 'edge-1']);
});

test('admins can rotate a node token', function () {
    $admin = User::factory()->admin()->create();
    $node = Node::factory()->create();
    $originalHash = $node->token_hash;

    $this->actingAs($admin)
        ->post(route('nodes.token', $node))
        ->assertRedirect(route('nodes.index'))
        ->assertSessionHas('plain_token');

    expect($node->fresh()->token_hash)->not->toBe($originalHash);
});
