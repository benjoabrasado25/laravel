<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every test starts with one confirmed booking from 10 to 15 March 2030
 * (check-in 10th, check-out morning of the 15th = nights 10, 11, 12, 13, 14).
 */
class BookingOverlapTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze "today" so the fixed dates below are always in the future.
        $this->travelTo('2030-03-01');

        $this->property = Property::factory()->create();

        Booking::factory()->for($this->property)->create([
            'check_in' => '2030-03-10',
            'check_out' => '2030-03-15',
        ]);
    }

    private function book(string $checkIn, string $checkOut, ?Property $property = null): TestResponse
    {
        $property ??= $this->property;

        return $this->post(route('bookings.store', $property), [
            'guest_name' => 'Test Guest',
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);
    }

    public function test_the_booking_form_loads(): void
    {
        $this->get(route('bookings.create', $this->property))
            ->assertOk()
            ->assertSee('Confirm booking');
    }

    public function test_a_free_stay_can_be_booked(): void
    {
        $this->book('2030-03-20', '2030-03-23')
            ->assertRedirect(route('properties.show', $this->property))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'property_id' => $this->property->id,
            'guest_name' => 'Test Guest',
            'status' => Booking::CONFIRMED,
        ]);
    }

    // --- Overlapping stays are rejected --------------------------------------

    public function test_a_stay_starting_inside_an_existing_booking_is_rejected(): void
    {
        $this->book('2030-03-12', '2030-03-18')->assertSessionHasErrors('dates');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_a_stay_ending_inside_an_existing_booking_is_rejected(): void
    {
        $this->book('2030-03-08', '2030-03-11')->assertSessionHasErrors('dates');
    }

    public function test_a_stay_that_wraps_around_an_existing_booking_is_rejected(): void
    {
        $this->book('2030-03-05', '2030-03-20')->assertSessionHasErrors('dates');
    }

    public function test_a_stay_that_sits_fully_inside_an_existing_booking_is_rejected(): void
    {
        $this->book('2030-03-11', '2030-03-13')->assertSessionHasErrors('dates');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_a_stay_with_exactly_the_same_dates_is_rejected(): void
    {
        $this->book('2030-03-10', '2030-03-15')->assertSessionHasErrors('dates');

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_the_overlap_error_message_names_the_clashing_dates(): void
    {
        $this->book('2030-03-12', '2030-03-18')->assertSessionHasErrors([
            'dates' => 'These dates overlap an existing confirmed booking (10 Mar 2030 to 15 Mar 2030). Please choose other dates.',
        ]);
    }

    // --- Adjacent stays are allowed ------------------------------------------

    public function test_checking_in_on_the_day_the_previous_guest_checks_out_is_allowed(): void
    {
        $this->book('2030-03-15', '2030-03-18')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_checking_out_on_the_day_the_next_guest_checks_in_is_allowed(): void
    {
        $this->book('2030-03-07', '2030-03-10')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_a_stay_that_exactly_fills_the_gap_between_two_bookings_is_allowed(): void
    {
        // Existing stays: 10-15 March and 20-25 March, leaving the nights 15-19 free.
        Booking::factory()->for($this->property)->create(['check_in' => '2030-03-20', 'check_out' => '2030-03-25']);

        $this->book('2030-03-14', '2030-03-20')->assertSessionHasErrors('dates');
        $this->book('2030-03-15', '2030-03-21')->assertSessionHasErrors('dates');
        $this->book('2030-03-15', '2030-03-20')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bookings', 3);
    }

    // --- Same-day cases ------------------------------------------------------

    public function test_a_stay_with_the_same_check_in_day_is_rejected(): void
    {
        $this->book('2030-03-10', '2030-03-11')->assertSessionHasErrors('dates');
    }

    public function test_a_one_night_stay_on_the_last_booked_night_is_rejected(): void
    {
        $this->book('2030-03-14', '2030-03-15')->assertSessionHasErrors('dates');
    }

    public function test_check_out_on_the_same_day_as_check_in_is_rejected(): void
    {
        $this->book('2030-03-20', '2030-03-20')->assertSessionHasErrors([
            'check_out' => 'Check-out must be at least one day after check-in.',
        ]);
    }

    // --- Only confirmed bookings on the same property block dates ------------

    public function test_pending_and_cancelled_bookings_do_not_block_dates(): void
    {
        Booking::factory()->for($this->property)->pending()->create(['check_in' => '2030-04-01', 'check_out' => '2030-04-05']);
        Booking::factory()->for($this->property)->cancelled()->create(['check_in' => '2030-04-10', 'check_out' => '2030-04-15']);

        $this->book('2030-04-02', '2030-04-04')->assertSessionHasNoErrors();
        $this->book('2030-04-11', '2030-04-13')->assertSessionHasNoErrors();
    }

    public function test_bookings_on_another_property_do_not_block_dates(): void
    {
        $otherProperty = Property::factory()->create();

        $this->book('2030-03-10', '2030-03-15', $otherProperty)->assertSessionHasNoErrors();
    }

    // --- Basic validation ----------------------------------------------------

    public function test_all_fields_are_required(): void
    {
        $this->post(route('bookings.store', $this->property), [])
            ->assertSessionHasErrors(['guest_name', 'check_in', 'check_out']);
    }

    public function test_check_out_before_check_in_is_rejected(): void
    {
        $this->book('2030-03-23', '2030-03-20')->assertSessionHasErrors([
            'check_out' => 'Check-out must be at least one day after check-in.',
        ]);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_check_in_cannot_be_in_the_past(): void
    {
        $this->book('2030-02-20', '2030-02-22')->assertSessionHasErrors('check_in');
    }

    public function test_check_in_today_is_allowed(): void
    {
        // "Today" is frozen at 1 March 2030 in setUp().
        $this->book('2030-03-01', '2030-03-03')->assertSessionHasNoErrors();
    }

    public function test_check_in_yesterday_is_rejected(): void
    {
        $this->book('2030-02-28', '2030-03-03')->assertSessionHasErrors([
            'check_in' => 'Check-in cannot be in the past.',
        ]);
    }
}
