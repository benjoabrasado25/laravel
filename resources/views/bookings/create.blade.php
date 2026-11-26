@extends('layouts.app')

@section('title', 'Book '.$property->name)

@section('content')
    <p><a href="{{ route('properties.show', $property) }}">&larr; Back to {{ $property->name }}</a></p>

    <h1>Book {{ $property->name }}</h1>
    <p class="muted">{{ $property->city }} · €{{ $property->price_per_night }} / night</p>

    {{-- The overlap error isn't tied to one field, so it's shown above the form. --}}
    @error('dates')
        <div class="alert alert-error">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('bookings.store', $property) }}" class="form">
        @csrf

        <div class="field">
            <label for="guest_name">Guest name</label>
            <input type="text" id="guest_name" name="guest_name" value="{{ old('guest_name') }}"
                   class="@error('guest_name') invalid @enderror" required maxlength="100">
            @error('guest_name') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="check_in">Check-in</label>
            <input type="date" id="check_in" name="check_in" value="{{ old('check_in') }}"
                   class="@error('check_in') invalid @enderror" required>
            @error('check_in') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="check_out">Check-out</label>
            <input type="date" id="check_out" name="check_out" value="{{ old('check_out') }}"
                   class="@error('check_out') invalid @enderror" required>
            @error('check_out') <p class="error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="button">Confirm booking</button>
    </form>
@endsection
