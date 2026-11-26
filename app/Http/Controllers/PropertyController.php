<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $city = $request->query('city');

        $properties = Property::query()
            ->when($city, fn ($query) => $query->where('city', $city))
            ->orderBy('name')
            ->get();

        // Cities for the filter dropdown come from the data, not a hard-coded list.
        $cities = Property::query()->distinct()->orderBy('city')->pluck('city');

        return view('properties.index', compact('properties', 'cities', 'city'));
    }

    public function show(Property $property): View
    {
        $bookings = $property->bookings()->orderBy('check_in')->get();

        return view('properties.show', compact('property', 'bookings'));
    }
}
