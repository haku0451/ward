<?php

use App\Models\AttackEvent;
use App\Models\Node;
use App\Models\User;

test('guests cannot view the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can view dashboard stats', function () {
    $user = User::factory()->create();
    $node = Node::factory()->online()->create();
    AttackEvent::factory()->create(['node_id' => $node->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('stats.detected', 1)
            ->where('stats.nodes', 1)
            ->where('stats.online', 1));
});
