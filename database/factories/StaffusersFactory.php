<?php

namespace Database\Factories;

use App\Models\Staffusers;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Staffusers>
 */
class StaffusersFactory extends Factory
{
    protected $model = Staffusers::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'passwordHash' => static::$password ??= Hash::make('password'),
            'officeId' => 1,
            'role' => 'staff',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }
}