<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageAndCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_switches_to_hindi(): void
    {
        $product = Product::factory()->create();

        $this->get('/')->assertSee('Many sellers. One cart. One checkout.')->assertSee('हिन्दी');

        $this->get('/language/hi')->assertRedirect()->assertCookie('locale', 'hi', false);

        $this->withUnencryptedCookie('locale', 'hi')->get('/')->assertSee('कई विक्रेता। एक कार्ट। एक चेकआउट।')->assertSee('lang="hi"', false);
        $this->withUnencryptedCookie('locale', 'hi')->get($product->url())->assertSee('कार्ट में डालें');
        $this->get('/language/fr')->assertNotFound();
    }

    public function test_browser_language_is_used_when_nothing_chosen(): void
    {
        $this->withHeader('Accept-Language', 'hi-IN,hi;q=0.9,en;q=0.8')->get('/')->assertSee('नए उत्पाद');
        $this->withHeader('Accept-Language', 'fr-FR')->get('/')->assertSee('New arrivals');
    }

    public function test_api_follows_accept_language(): void
    {
        $this->withHeader('Accept-Language', 'hi')->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'x'])
            ->assertJsonPath('errors.email.0', 'ये विवरण हमारे रिकॉर्ड से मेल नहीं खाते।');
        $this->getJson('/api/v1/config')->assertJsonPath('currency_rates.USD', 0.012)->assertJsonPath('languages', ['en', 'hi']);
    }

    public function test_prices_can_be_shown_in_another_currency_but_charged_in_inr(): void
    {
        app(Settings::class)->set(['currency_rates' => ['USD' => 0.012]]);
        $product = Product::factory()->priced(250000)->create();

        $this->get('/currency/USD')->assertRedirect()->assertCookie('currency', 'USD', false);
        $this->get('/currency/XYZ')->assertNotFound();

        $this->get($product->url())->assertDontSee('≈ $');
        // Test cookies persist, so the USD checks come last.
        $this->withUnencryptedCookie('currency', 'USD')->get($product->url())->assertSee('≈ $30.00')->assertSee('(₹2,500.00)');

        $this->actingAs(User::factory()->create())->withUnencryptedCookie('currency', 'USD')->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 1]);
        $this->withUnencryptedCookie('currency', 'USD')->get('/cart')->assertSee('You pay in Indian Rupees');
    }
}
