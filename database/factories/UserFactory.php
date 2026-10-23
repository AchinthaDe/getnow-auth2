<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= 'password',
            'role' => UserRole::Customer,
        ];
    }

    /**
     * Indicate that the model's role should be superadmin.
     */
    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Superadmin,
        ]);
    }

    /**
     * Indicate that the model's role should be retailer.
     */
    public function retailer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Retailer,
        ]);
    }

    /**
     * Indicate that the model's role should be customer.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Customer,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
