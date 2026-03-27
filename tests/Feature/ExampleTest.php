<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_is_available(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create());

        $response = $this->get('/worksheets');

        $response->assertOk();
    }
}
