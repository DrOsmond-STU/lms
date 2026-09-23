<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Data sintetis saja — tidak pernah data produksi (docs/10 §4).
 *
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    /** Kata sandi uji; di-hash sekali per proses agar uji tetap cepat. */
    public const PASSWORD = 'Rahasia-Uji-2026!';

    private static ?string $passwordHash = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->userName().'@example.test',
            'email_verified_at' => now(),
            'password' => self::$passwordHash ??= password_hash(self::PASSWORD, PASSWORD_ARGON2ID, ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]),
            'status' => 'active',
            'session_version' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'deactivated', 'deactivated_at' => now()]);
    }
}
