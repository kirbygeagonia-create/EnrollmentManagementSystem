<?php

namespace Database\Factories;

use App\Models\Offices;
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

    public function configure(): static
    {
        return $this->afterMaking(function (Staffusers $staffuser) {
            // Ensure the FK target exists before the staffuser insert.
            Offices::firstOrCreate(
                ['officeId' => $staffuser->officeId],
                ['officeName' => 'Office '.$staffuser->officeId]
            );
        });
    }

    public function definition(): array
    {
        return [
            'employeeNo' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'firstName' => fake()->firstName(),
            'middleName' => fake()->lastName(),
            'lastName' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'passwordHash' => static::$password ??= Hash::make('password'),
            'officeId' => 1,
            'contactNo' => fake()->numerify('09##########'),
            'role' => 'staff',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }
}
