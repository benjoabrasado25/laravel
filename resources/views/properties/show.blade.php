@extends('layouts.app')

@section('title', $property->name)

@section('content')
    <section class="container page-top">
        <a href="{{ route('properties.index') }}" class="back-link">&larr; All properties</a>

        <div class="property-hero">
            <div class="media media-wide tint-{{ Str::slug($property->city) }}">
                @include('partials.icon-house', ['size' => 72, 'stroke' => 1.4])
                <span class="media-chip">{{ $property->city }}</span>
                <span class="media-note">Photo coming soon</span>
            </div>

            <div class="property-summary">
                <p class="eyebrow">{{ $property->location() }}</p>
                <h1 class="title">{{ $property->name }}</h1>
                <p class="fact">
                    @include('partials.icon-bed')
                    {{ $property->bedrooms }} {{ Str::plural('bedroom', $property->bedrooms) }}
                </p>
                <div class="summary-footer">
                    <p class="price"><strong>€{{ $property->price_per_night }}</strong> / night</p>
                    <a href="{{ route('bookings.create', $property) }}" class="button">Book this property</a>
                </div>
            </div>
        </div>
    </section>

    <section class="container section">
        <div class="section-head">
            <h2 class="section-title">Bookings</h2>
            <span class="muted">{{ $bookings->count() }} {{ Str::plural('booking', $bookings->count()) }}</span>
        </div>

        @if ($bookings->isEmpty())
            <div class="empty-state">
                <p>No bookings yet.</p>
            </div>
        @else
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Guest</th>
                            <th scope="col">Check-in</th>
                            <th scope="col">Check-out</th>
                            <th scope="col">Nights</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            <tr>
                                <td class="strong" data-label="Guest">{{ $booking->guest_name }}</td>
                                <td data-label="Check-in">{{ $booking->check_in->format('D j M Y') }}</td>
                                <td data-label="Check-out">{{ $booking->check_out->format('D j M Y') }}</td>
                                <td data-label="Nights">{{ $booking->nights() }}</td>
                                <td data-label="Status"><span class="badge badge-{{ $booking->status }}">{{ $booking->status }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
