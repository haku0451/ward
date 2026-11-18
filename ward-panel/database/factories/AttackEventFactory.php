<?php

namespace Database\Factories;

use App\Enums\EventAction;
use App\Models\AttackEvent;
use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttackEvent>
 */
class AttackEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'node_id' => Node::factory(),
            'site_id' => null,
            'hostname' => fake()->domainName(),
            'client_ip' => fake()->ipv4(),
            'method' => 'GET',
            'path' => '/'.fake()->word(),
            'rule_id' => '941100',
            'rule_message' => 'XSS Attack Detected',
            'action' => EventAction::Detect,
            'http_status' => 403,
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
        ];
    }
}
