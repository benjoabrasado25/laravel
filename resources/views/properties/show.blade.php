@extends('layouts.app')

@section('title', $property->name)

@section('content')
    <p><a href="{{ route('properties.index') }}">&larr; All properties</a></p>

    <div class="page-head">
        <div>
            <h1>{{ $property->name }}</h1>
            <p class="muted">
                {{ $property->city }}
                · €{{ $property->price_per_night }} / night
                · {{ $property->bedrooms }} {{ Str::plural('bedroom', $property->bedrooms) }}
            </p>
        </div>
    </div>

    <h2>Bookings</h2>

    @if ($bookings->isEmpty())
        <p class="empty">No bookings yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Guest</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Nights</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bookings as $booking)
                    <tr>
                        <td>{{ $booking->guest_name }}</td>
                        <td>{{ $booking->check_in->format('D j M Y') }}</td>
                        <td>{{ $booking->check_out->format('D j M Y') }}</td>
                        <td>{{ $booking->nights() }}</td>
                        <td><span class="badge badge-{{ $booking->status }}">{{ $booking->status }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
