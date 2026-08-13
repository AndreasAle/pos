<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name'      => $name,
            'slug'      => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 999999),
            'phone'     => fake()->numerify('08##########'),
            'email'     => fake()->unique()->safeEmail(),
            'is_active' => true,
            'settings'  => [],
        ];
    }

    /**
     * Merge extra keys into the settings JSON column.
     *
     * @param  array<string, mixed>  $settings
     */
    public function settings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], $settings),
        ]);
    }
}
