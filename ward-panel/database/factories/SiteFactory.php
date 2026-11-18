<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $host = fake()->unique()->domainName();

        return [
            'name' => fake()->company(),
            'hostname' => $host,
            'origin_url' => 'http://127.0.0.1:9080',
            'waf_mode' => null,
            'node_id' => null,
        ];
    }
}
