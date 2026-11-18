<?php

use App\Models\Node;
use App\Models\Site;
use App\Models\User;

test('admins can create a node from the web form', function () {
    $admin = User::factory()->admin()->create();
    Site::factory()->create([
        'hostname' => 'localhost',
        'origin_url' => 'http://127.0.0.1:9080',
        'node_id' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('nodes.store'), [
            'name' => 'edge-1',
            'hostname' => 'waf-1.example.test',
            'waf_mode' => 'blocking',
        ])
        ->assertRedirect(route('nodes.index'))
        ->assertSessionHas('plain_token')
        ->assertSessionHas('agent_yaml')
        ->assertSessionHas('status');

    $yaml = session('agent_yaml');

    expect($yaml)->toContain('listen: ":8080"')
        ->and($yaml)->toContain('token: "'.session('plain_token').'"')
        ->and($yaml)->toContain('mode: blocking')
        ->and($yaml)->toContain('hostname: "localhost"')
        ->and($yaml)->toContain('origin_url: "http://127.0.0.1:9080"')
        ->and($yaml)->toContain('policy_public_key:');

    $this->assertDatabaseHas('nodes', [
        'name' => 'edge-1',
        'hostname' => 'waf-1.example.test',
        'waf_mode' => 'blocking',
    ]);
});

test('generated agent yaml uses docker panel url when configured', function () {
    config([
        'ward.agent_panel_url' => 'http://panel:8000',
        'ward.agent_data_dir' => '/data',
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('nodes.store'), ['name' => 'edge-docker'])
        ->assertRedirect(route('nodes.index'));

    $yaml = session('agent_yaml');

    expect($yaml)->toContain('url: "http://panel:8000"')
        ->and($yaml)->toContain('data_dir: "/data"')
        ->and($yaml)->toContain('path: "/data/events.jsonl"');
});

test('creating a node requires a name', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('nodes.index'))
        ->post(route('nodes.store'), [
            'name' => '',
            'waf_mode' => 'detection_only',
        ])
        ->assertRedirect(route('nodes.index'))
        ->assertSessionHasErrors('name');

    expect(Node::query()->count())->toBe(0);
});

test('admins can generate agent yaml without rotating the token', function () {
    $admin = User::factory()->admin()->create();
    $node = Node::factory()->create(['name' => 'edge-2']);

    $this->actingAs($admin)
        ->post(route('nodes.agent-config', $node))
        ->assertRedirect(route('nodes.index'))
        ->assertSessionHas('agent_yaml');

    expect(session('agent_yaml'))
        ->toContain('token: "${WARD_AGENT_TOKEN}"')
        ->and(session('agent_yaml'))->toContain('listen: ":8080"')
        ->and(session('agent_yaml'))->not->toContain('#');
});

test('viewers cannot create nodes', function () {
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->post(route('nodes.store'), ['name' => 'edge-1'])
        ->assertForbidden();
});
