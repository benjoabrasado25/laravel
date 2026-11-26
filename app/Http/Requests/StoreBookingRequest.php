<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // no login in this demo
    }

    public function rules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'max:100'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'], // at least 1 night
        ];
    }

    public function messages(): array
    {
        return [
            'check_in.after_or_equal' => 'Check-in cannot be in the past.',
            'check_out.after' => 'Check-out must be at least one day after check-in.',
        ];
    }

    /**
     * Checks that run after the basic rules above. This is where the business
     * rule lives: the stay must not overlap a confirmed booking.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Only check for overlaps once the dates themselves are valid.
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $clash = $this->route('property')->findOverlappingBooking(
                    $this->input('check_in'),
                    $this->input('check_out'),
                );

                if ($clash) {
                    $validator->errors()->add('dates', sprintf(
                        'These dates overlap an existing confirmed booking (%s to %s). Please choose other dates.',
                        $clash->check_in->format('j M Y'),
                        $clash->check_out->format('j M Y'),
                    ));
                }
            },
        ];
    }
}
