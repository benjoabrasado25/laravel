<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    /** Sort options from the "Sort by" dropdown: key => [column, direction]. */
    private const SORTS = [
        'price-asc' => ['price_per_night', 'asc'],
        'price-desc' => ['price_per_night', 'desc'],
        'beds' => ['bedrooms', 'desc'],
    ];

    public function index(Request $request): View
    {
        $city = $this->stringQuery($request, 'city');
        $sort = $this->stringQuery($request, 'sort');

        if (! isset(self::SORTS[$sort])) {
            $sort = null; // unknown or missing: "Recommended", which is A-Z by name
        }

        [$column, $direction] = self::SORTS[$sort] ?? ['name', 'asc'];

        $properties = Property::query()
            ->when($city, fn ($query) => $query->where('city', $city))
            ->orderBy($column, $direction)
            ->orderBy('name') // tie-breaker, so equal prices always show in the same order
            ->get();

        // City pills and hero text come from the data: ['Barcelona' => 2, 'Lisbon' => 2, ...]
        $cityCounts = Property::query()
            ->selectRaw('city, count(*) as total')
            ->groupBy('city')
            ->orderBy('city')
            ->pluck('total', 'city');

        $countries = $cityCounts->keys()
            ->map(fn ($name) => Property::COUNTRIES[$name] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('properties.index', [
            'properties' => $properties,
            'city' => $city,
            'sort' => $sort,
            'cityCounts' => $cityCounts,
            'countries' => $countries,
            'total' => $cityCounts->sum(),
            'minPrice' => Property::min('price_per_night'),
        ]);
    }

    public function show(Property $property): View
    {
        $bookings = $property->bookings()->orderBy('check_in')->get();

        return view('properties.show', compact('property', 'bookings'));
    }

    /**
     * Read a query parameter as plain text. Something like ?city[]=x arrives
     * as an array, so anything that isn't a non-empty string counts as "not set".
     */
    private function stringQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
