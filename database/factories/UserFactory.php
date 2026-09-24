<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Owner,
            'locale' => 'en',
            'last_seen_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function founder(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Founder]);
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Owner]);
    }

    public function invited(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Invited]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }
}
