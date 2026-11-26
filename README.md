# Stays: a small property booking demo

A small Laravel 13 app for browsing holiday rentals and booking them. You can
list properties and filter them by city, open a property to see its bookings,
and book a stay through a form. The main business rule: a new booking must not
overlap an existing **confirmed** booking for the same property. A guest may
check in on the day the previous guest checks out. The app uses SQLite, Blade
views and plain CSS, so it needs no external services and no npm or build
step. All data is generated dummy data.

## Requirements

- PHP 8.4+ with the `pdo_sqlite` extension (the locked Symfony 8 packages need 8.4.1 or newer)
- Composer

## Install and run

```bash
git clone https://github.com/benjoabrasado25/laravel.git stays
cd stays
composer setup        # installs packages, creates .env and the SQLite file, migrates and seeds
php artisan serve     # then open the URL it prints (usually http://127.0.0.1:8000)
```

## Useful commands

```bash
php artisan test                  # run the feature tests
php artisan migrate:fresh --seed  # reset the database to the demo data (8 properties, 20 bookings)
```

## Where things are

| What | File |
|---|---|
| Routes | `routes/web.php` |
| Models | `app/Models/Property.php`, `app/Models/Booking.php` |
| Overlap rule (query) | `Property::findOverlappingBooking()` |
| Validation + overlap check | `app/Http/Requests/StoreBookingRequest.php` |
| Controllers | `app/Http/Controllers/PropertyController.php`, `BookingController.php` |
| Views | `resources/views/` (layout, properties, bookings) |
| Styles | `public/css/app.css` |
| Migrations, factories, seeder | `database/` |
| Tests | `tests/Feature/PropertyPagesTest.php`, `tests/Feature/BookingOverlapTest.php`, `tests/Feature/BookingInputTest.php` |

## How the overlap rule works

A stay covers the nights from check-in up to, but not including, check-out.
Two stays overlap when each starts before the other ends:

```
existing.check_in < new.check_out  AND  existing.check_out > new.check_in
```

Because the comparisons are strict, back-to-back stays are allowed. Only
`confirmed` bookings block dates; `pending` and `cancelled` ones do not.
