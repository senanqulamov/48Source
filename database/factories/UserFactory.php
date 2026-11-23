<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(Str::random(24)),
            'role' => 'buyer',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function supplier(?Supplier $supplier = null): static
    {
        return $this->state(fn () => ['role' => 'supplier', 'supplier_id' => $supplier?->id ?? Supplier::factory()]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
