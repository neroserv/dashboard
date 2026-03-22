<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ResellerDomain;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResellerDomain>
 */
class ResellerDomainFactory extends Factory
{
    protected $model = ResellerDomain::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->domainWord();

        return [
            'brand_id' => Brand::query()->value('id') ?? Brand::query()->create([
                'key' => 'factory-'.fake()->unique()->slug(2),
                'name' => 'Factory Brand',
                'domains' => ['localhost.test'],
                'is_default' => ! Brand::query()->exists(),
            ])->id,
            'domain' => $name.'.'.fake()->randomElement(['de', 'com', 'net']),
            'user_id' => null,
            'skrime_id' => null,
            'status' => 'active',
            'registered_at' => now(),
            'expires_at' => now()->addYear(),
            'auto_renew' => false,
            'purchase_price' => null,
            'sale_price' => null,
            'tld' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }
}
