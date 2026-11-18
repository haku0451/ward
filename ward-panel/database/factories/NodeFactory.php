<?php

namespace Database\Factories;

use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = Node::generatePlainToken();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->unique()->domainWord().'-node',
            'token_prefix' => Node::tokenPrefix($plain),
            'token_hash' => Node::hashToken($plain),
            'hostname' => null,
            'agent_version' => null,
            'config_version' => null,
            'config_hash' => null,
            'last_seen_at' => null,
        ];
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_seen_at' => now(),
            'agent_version' => '0.1.0',
        ]);
    }
}
