<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function create(Property $property): View
    {
        return view('bookings.create', compact('property'));
    }

    /**
     * By the time this runs, StoreBookingRequest has already validated the
     * input and checked for overlaps. If it failed, Laravel redirected back
     * to the form with the errors and the old input.
     */
    public function store(StoreBookingRequest $request, Property $property): RedirectResponse
    {
        $property->bookings()->create([
            ...$request->validated(),
            'status' => Booking::CONFIRMED, // the demo confirms new bookings straight away
        ]);

        return redirect()
            ->route('properties.show', $property)
            ->with('success', 'Booking confirmed.');
    }
}
