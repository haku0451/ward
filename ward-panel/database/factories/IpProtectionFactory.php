<?php

namespace Database\Factories;

use App\Models\IpProtection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpProtection>
 */
class IpProtectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cidr' => fake()->unique()->ipv4().'/32',
            'reason' => 'management',
        ];
    }
}
