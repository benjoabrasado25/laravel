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

    public function test_nav_marks_properties_as_current_page_only_on_the_list(): void
    {
        $property = Property::factory()->create();

        $this->get(route('properties.index'))->assertSee('aria-current="page"', false);
        $this->get(route('properties.show', $property))->assertDontSee('aria-current="page"', false);
        $this->get(route('bookings.create', $property))->assertDontSee('aria-current="page"', false);
    }

    public function test_unknown_property_returns_404(): void
    {
        $this->get('/properties/999')->assertNotFound();
    }

    // --- Sorting and city pills (from the design guide) ----------------------

    private function makeThreeProperties(): void
    {
        Property::factory()->create(['name' => 'Budget Flat', 'city' => 'Porto', 'price_per_night' => 30, 'bedrooms' => 2]);
        Property::factory()->create(['name' => 'Central Loft', 'city' => 'Lisbon', 'price_per_night' => 90, 'bedrooms' => 1]);
        Property::factory()->create(['name' => 'Avenue House', 'city' => 'Lisbon', 'price_per_night' => 60, 'bedrooms' => 4]);
    }

    public function test_properties_are_sorted_by_name_by_default(): void
    {
        $this->makeThreeProperties();

        $this->get('/properties')->assertSeeInOrder(['Avenue House', 'Budget Flat', 'Central Loft']);
    }

    public function test_properties_can_be_sorted_by_price_low_to_high(): void
    {
        $this->makeThreeProperties();

        $this->get('/properties?sort=price-asc')->assertSeeInOrder(['Budget Flat', 'Avenue House', 'Central Loft']);
    }

    public function test_properties_can_be_sorted_by_price_high_to_low(): void
    {
        $this->makeThreeProperties();

        $this->get('/properties?sort=price-desc')->assertSeeInOrder(['Central Loft', 'Avenue House', 'Budget Flat']);
    }

    public function test_properties_can_be_sorted_by_most_bedrooms(): void
    {
        $this->makeThreeProperties();

        $this->get('/properties?sort=beds')->assertSeeInOrder(['Avenue House', 'Budget Flat', 'Central Loft']);
    }

    public function test_an_unknown_sort_falls_back_to_name_order(): void
    {
        $this->makeThreeProperties();

        $this->get('/properties?sort=bogus')
            ->assertOk()
            ->assertSeeInOrder(['Avenue House', 'Budget Flat', 'Central Loft']);
    }

    public function test_sort_and_city_filter_work_together(): void
    {
        $this->makeThreeProperties();

        $this->get('/properties?city=Lisbon&sort=price-asc')
            ->assertSeeInOrder(['Avenue House', 'Central Loft'])
            ->assertDontSee('Budget Flat');
    }

    public function test_city_pills_show_counts_and_keep_the_current_sort(): void
    {
        $this->makeThreeProperties();

        $this->get('/properties?sort=beds')
            ->assertSee('Showing <strong>3</strong>', false)
            ->assertSee('href="'.e(route('properties.index', ['city' => 'Lisbon', 'sort' => 'beds'])).'"', false)
            ->assertSeeInOrder(['Lisbon', '<span class="pill-count">2</span>'], false);
    }
}
