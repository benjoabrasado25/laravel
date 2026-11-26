<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_properties_list(): void
    {
        $this->get('/')->assertRedirect('/properties');
    }

    public function test_properties_list_shows_all_properties(): void
    {
        Property::factory()->create(['name' => 'Alfama Rooftop Loft', 'city' => 'Lisbon']);
        Property::factory()->create(['name' => 'Ribeira River Flat', 'city' => 'Porto']);

        $this->get('/properties')
            ->assertOk()
            ->assertSee('Alfama Rooftop Loft')
            ->assertSee('Ribeira River Flat');
    }

    public function test_properties_list_can_be_filtered_by_city(): void
    {
        Property::factory()->create(['name' => 'Alfama Rooftop Loft', 'city' => 'Lisbon']);
        Property::factory()->create(['name' => 'Ribeira River Flat', 'city' => 'Porto']);

        $this->get('/properties?city=Lisbon')
            ->assertOk()
            ->assertSee('Alfama Rooftop Loft')
            ->assertDontSee('Ribeira River Flat');
    }

    public function test_property_page_shows_its_bookings_only(): void
    {
        $property = Property::factory()->create();
        Booking::factory()->for($property)->create(['guest_name' => 'Ana Example']);
        Booking::factory()->create(['guest_name' => 'Someone Elsewhere']); // a different property

        $this->get("/properties/{$property->id}")
            ->assertOk()
            ->assertSee($property->name)
            ->assertSee('Ana Example')
            ->assertDontSee('Someone Elsewhere');
    }

    public function test_unknown_property_returns_404(): void
    {
        $this->get('/properties/999')->assertNotFound();
    }
}
