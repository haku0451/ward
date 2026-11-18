<?php

namespace Database\Factories;

use App\Enums\ApplyStatus;
use App\Enums\IpBlockSource;
use App\Models\IpBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IpBlock>
 */
class IpBlockFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cidr' => fake()->unique()->ipv4().'/32',
            'source' => IpBlockSource::Manual,
            'reason' => 'test',
            'apply_status' => ApplyStatus::Pending,
        ];
    }
}
