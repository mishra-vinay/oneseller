<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory()->seller(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'phone' => fake()->numerify('98########'),
            'address_line1' => fake()->streetAddress(),
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pincode' => '411001',
            'status' => StoreStatus::Approved,
            'approved_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => StoreStatus::Pending, 'approved_at' => null]);
    }
}
