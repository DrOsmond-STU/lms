<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Organization\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => 'Organisasi '.fake()->unique()->company(),
            'code' => strtoupper(fake()->unique()->lexify('?????')),
            'type' => 'institution',
            'status' => 'active',
            'settings' => [],
        ];
    }

    public function corporate(): static
    {
        return $this->state(fn () => ['type' => 'corporate']);
    }
}
