<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('OneSeller')->assertSee('Sign up');
    }
}
