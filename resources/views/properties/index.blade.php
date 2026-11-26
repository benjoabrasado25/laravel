@extends('layouts.app')

@section('title', 'Properties')

@section('content')
    <section class="container hero">
        @if ($countries->isNotEmpty())
            <p class="eyebrow">{{ $countries->join(' & ') }}</p>
        @endif
        <h1 class="display">
            Places to stay{{ $cityCounts->isNotEmpty() ? ' in '.$cityCounts->keys()->join(', ', ' and ') : '' }}
        </h1>
        @if ($total > 0)
            <p class="lead">{{ $total }} hand-picked {{ Str::plural('property', $total) }}, from €{{ $minPrice }} a night.</p>
        @endif
    </section>

    <section class="container" aria-label="Filters">
        <div class="filter-bar">
            {{-- City pills are plain links, so each one keeps the current sort. --}}
            <nav aria-label="City" class="city-pills">
                <a href="{{ route('properties.index', array_filter(['sort' => $sort])) }}"
                   class="pill" @if (! $city) aria-current="true" @endif>
                    All cities <span class="pill-count">{{ $total }}</span>
                </a>
                @foreach ($cityCounts as $name => $count)
                    <a href="{{ route('properties.index', array_filter(['city' => $name, 'sort' => $sort])) }}"
                       class="pill" @if ($name === $city) aria-current="true" @endif>
                        {{ $name }} <span class="pill-count">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- The sort form carries the current city along in a hidden field. --}}
            <form method="GET" action="{{ route('properties.index') }}" class="sort">
                @if ($city)
                    <input type="hidden" name="city" value="{{ $city }}">
                @endif
                <label for="sort">Sort by</label>
                <select id="sort" name="sort" onchange="this.form.submit()">
                    <option value="">Recommended</option>
                    <option value="price-asc" @selected($sort === 'price-asc')>Price: low to high</option>
                    <option value="price-desc" @selected($sort === 'price-desc')>Price: high to low</option>
                    <option value="beds" @selected($sort === 'beds')>Most bedrooms</option>
                </select>
                <noscript><button type="submit" class="button button-secondary">Apply</button></noscript>
            </form>
        </div>
    </section>

    <section class="container results">
        <p class="results-count">
            Showing <strong>{{ $properties->count() }}</strong>
            {{ Str::plural('property', $properties->count()) }}{{ $city ? ' in '.$city : '' }}
        </p>

        @if ($properties->isEmpty())
            <div class="empty-state">
                <p>No properties found.</p>
                <a href="{{ route('properties.index', array_filter(['sort' => $sort])) }}">Show all cities</a>
            </div>
        @else
            <div class="card-grid">
                @foreach ($properties as $property)
                    <article class="card">
                        <div class="media tint-{{ Str::slug($property->city) }}">
                            @include('partials.icon-house', ['size' => 56, 'stroke' => 1.4])
                            <span class="media-chip">{{ $property->city }}</span>
                            <span class="media-note">Photo coming soon</span>
                        </div>
                        <div class="card-body">
                            <div>
                                <h2 class="card-title">{{ $property->name }}</h2>
                                <p class="card-location">{{ $property->location() }}</p>
                            </div>
                            <p class="fact">
                                @include('partials.icon-bed')
                                {{ $property->bedrooms }} {{ Str::plural('bedroom', $property->bedrooms) }}
                            </p>
                            <div class="card-footer">
                                <p class="price"><strong>€{{ $property->price_per_night }}</strong> / night</p>
                                <a href="{{ route('properties.show', $property) }}" class="button"
                                   aria-label="View stay: {{ $property->name }}">View stay</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
