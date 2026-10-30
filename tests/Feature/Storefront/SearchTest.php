<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * Migrates fresh instead of using a wrapping transaction: MySQL FULLTEXT indexes
 * only see committed rows, so search must be tested against committed data.
 */
class SearchTest extends TestCase
{
    use DatabaseMigrations;

    public function test_search_matches_names_and_descriptions(): void
    {
        Product::factory()->create(['name' => 'Bluetooth Speaker Mini', 'description' => 'Loud and clear']);
        Product::factory()->create(['name' => 'Desk Lamp', 'description' => 'Works with a bluetooth remote']);
        Product::factory()->create(['name' => 'Cotton Saree', 'description' => 'Handloom weave']);

        $this->get('/search?q=bluetooth')->assertOk()
            ->assertSee('Bluetooth Speaker Mini')
            ->assertSee('Desk Lamp')
            ->assertDontSee('Cotton Saree');

        $this->get('/search?q=bluetooth+speaker')->assertSee('Bluetooth Speaker Mini')->assertDontSee('Desk Lamp');
        $this->get('/search?q=blue')->assertSee('Bluetooth Speaker Mini');
        $this->get('/search')->assertOk()->assertSee('Cotton Saree');
    }
}
