<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Input handling and basic security checks for the booking form and the
 * city filter. CSRF is not tested here: Laravel skips the CSRF check while
 * running tests, so that is checked by hand against the running app.
 */
class BookingInputTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2030-03-01');

        $this->property = Property::factory()->create();
    }

    private function bookAs(string $guestName, string $checkIn = '2030-03-10', string $checkOut = '2030-03-12'): TestResponse
    {
        return $this->post(route('bookings.store', $this->property), [
            'guest_name' => $guestName,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);
    }

    // --- Guest name ----------------------------------------------------------

    public function test_guest_name_is_required(): void
    {
        $this->bookAs('')->assertSessionHasErrors('guest_name');
        $this->bookAs('   ')->assertSessionHasErrors('guest_name'); // trimmed to empty

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_guest_name_can_be_100_characters(): void
    {
        $this->bookAs(str_repeat('a', 100))->assertSessionHasNoErrors();
    }

    public function test_guest_name_cannot_be_101_characters(): void
    {
        $this->bookAs(str_repeat('a', 101))->assertSessionHasErrors('guest_name');
    }

    public function test_guest_name_length_counts_characters_not_bytes(): void
    {
        // 100 two-byte characters is 200 bytes but still only 100 characters.
        $this->bookAs(str_repeat('é', 100))->assertSessionHasNoErrors();
    }

    public function test_unicode_guest_name_is_stored_and_shown(): void
    {
        $this->bookAs('José Müller')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['guest_name' => 'José Müller']);
        $this->get(route('properties.show', $this->property))->assertSee('José Müller');
    }

    public function test_html_in_guest_name_is_escaped_on_the_property_page(): void
    {
        $this->bookAs('<script>alert(1)</script>')->assertSessionHasNoErrors();

        $this->get(route('properties.show', $this->property))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // --- Dates ---------------------------------------------------------------

    public function test_dates_in_the_wrong_format_are_rejected(): void
    {
        $this->bookAs('Test Guest', '10/03/2030', '12/03/2030')
            ->assertSessionHasErrors(['check_in', 'check_out']);
    }

    public function test_nonsense_dates_are_rejected(): void
    {
        $this->bookAs('Test Guest', 'next tuesday', 'banana')
            ->assertSessionHasErrors(['check_in', 'check_out']);
    }

    public function test_impossible_dates_are_rejected(): void
    {
        $this->bookAs('Test Guest', '2030-02-30', '2030-03-02')->assertSessionHasErrors('check_in');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_each_missing_field_is_reported(): void
    {
        $this->post(route('bookings.store', $this->property), ['check_in' => '2030-03-10', 'check_out' => '2030-03-12'])
            ->assertSessionHasErrors('guest_name');
        $this->post(route('bookings.store', $this->property), ['guest_name' => 'Test Guest', 'check_out' => '2030-03-12'])
            ->assertSessionHasErrors('check_in');
        $this->post(route('bookings.store', $this->property), ['guest_name' => 'Test Guest', 'check_in' => '2030-03-10'])
            ->assertSessionHasErrors('check_out');
    }

    // --- Unknown property ----------------------------------------------------

    public function test_booking_form_for_an_unknown_property_returns_404(): void
    {
        $this->get('/properties/999/bookings/create')->assertNotFound();
    }

    public function test_posting_a_booking_for_an_unknown_property_returns_404(): void
    {
        $this->post('/properties/999/bookings', [
            'guest_name' => 'Test Guest',
            'check_in' => '2030-03-10',
            'check_out' => '2030-03-12',
        ])->assertNotFound();

        $this->assertDatabaseCount('bookings', 0);
    }

    // --- Old input and mass assignment ---------------------------------------

    public function test_old_input_is_kept_after_a_validation_error(): void
    {
        $this->from(route('bookings.create', $this->property))
            ->followingRedirects()
            ->post(route('bookings.store', $this->property), [
                'guest_name' => 'Ana Example',
                'check_in' => '2030-03-12',
                'check_out' => '2030-03-10',
            ])
            ->assertOk()
            ->assertSee('value="Ana Example"', false)
            ->assertSee('value="2030-03-12"', false)
            ->assertSee('value="2030-03-10"', false)
            ->assertSee('Check-out must be at least one day after check-in.');
    }

    public function test_status_and_property_id_cannot_be_set_from_the_form(): void
    {
        $otherProperty = Property::factory()->create();

        $this->post(route('bookings.store', $this->property), [
            'guest_name' => 'Test Guest',
            'check_in' => '2030-03-10',
            'check_out' => '2030-03-12',
            'status' => Booking::CANCELLED,
            'property_id' => $otherProperty->id,
        ])->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame(Booking::CONFIRMED, $booking->status);
        $this->assertSame($this->property->id, $booking->property_id);
    }

    // --- City filter ---------------------------------------------------------

    public function test_unknown_city_shows_an_empty_list(): void
    {
        $this->get('/properties?city=Atlantis')
            ->assertOk()
            ->assertSee('No properties found.')
            ->assertDontSee($this->property->name);
    }

    public function test_unicode_city_filter_matches(): void
    {
        $match = Property::factory()->create(['name' => 'Unicode Test Flat', 'city' => 'São Paulo']);

        $this->get('/properties?city='.urlencode('São Paulo'))
            ->assertOk()
            ->assertSee($match->name)
            ->assertDontSee($this->property->name);
    }

    public function test_sql_injection_in_city_filter_is_harmless(): void
    {
        $this->get('/properties?city='.urlencode("'; DROP TABLE properties;--"))
            ->assertOk()
            ->assertSee('No properties found.');

        $this->assertTrue(Schema::hasTable('properties'));
        $this->assertDatabaseCount('properties', 1);
    }

    public function test_city_filter_given_as_an_array_does_not_crash(): void
    {
        $this->get('/properties?city[]=x')->assertOk();
    }

    public function test_html_in_city_filter_is_not_echoed_unescaped(): void
    {
        $payload = '"><script>alert(1)</script>';

        $this->get('/properties?city='.urlencode($payload))
            ->assertOk()
            ->assertDontSee($payload, false);
    }
}
