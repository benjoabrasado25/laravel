@extends('layouts.app')

@section('title', 'Properties')

@section('content')
    <div class="page-head">
        <h1>Properties</h1>

        {{-- A plain GET form: the selected city ends up in the URL as ?city=Lisbon --}}
        <form method="GET" action="{{ route('properties.index') }}" class="filter">
            <label for="city">City</label>
            <select name="city" id="city" onchange="this.form.submit()">
                <option value="">All cities</option>
                @foreach ($cities as $option)
                    <option value="{{ $option }}" @selected($option === $city)>{{ $option }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="button">Filter</button></noscript>
        </form>
    </div>

    @if ($properties->isEmpty())
        <p class="empty">No properties found.</p>
    @else
        <div class="grid">
            @foreach ($properties as $property)
                <a href="{{ route('properties.show', $property) }}" class="card">
                    <h2>{{ $property->name }}</h2>
                    <p class="muted">{{ $property->city }}</p>
                    <p>
                        <strong>€{{ $property->price_per_night }}</strong> / night
                        · {{ $property->bedrooms }} {{ Str::plural('bedroom', $property->bedrooms) }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif
@endsection
