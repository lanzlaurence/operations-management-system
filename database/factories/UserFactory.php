<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * A factory user is ready to browse the application: verified, active and
     * past the forced password change. Without those flags the `active` and
     * `password.changed` middleware bounce every request, which is what used
     * to make the feature tests redirect instead of rendering.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'password_changed_at' => now(),
            'force_password_change' => false,
            'is_active' => true,
            'is_locked' => false,
            'login_attempts' => 0,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * A deactivated account: authenticated requests are rejected by the
     * `active` middleware.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * An account locked out after too many failed logins.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_locked' => true,
        ]);
    }

    /**
     * An account that must change its password before it can go anywhere.
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'force_password_change' => true,
            'password_changed_at' => null,
        ]);
    }
}
