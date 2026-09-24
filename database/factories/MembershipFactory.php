<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\Agency;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'user_id' => User::factory()->owner(),
            'role' => MembershipRole::Owner,
            'commission_rate' => null,
        ];
    }

    public function invited(int $commissionRate = 40): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->invited(),
            'role' => MembershipRole::Invited,
            'commission_rate' => $commissionRate,
        ]);
    }
}
