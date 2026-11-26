# Stays: a small property booking demo

A small Laravel 13 app for browsing holiday rentals and booking them. You can
list properties, filter them by city, sort them by price or bedrooms, open a
property to see its bookings, and book a stay through a form. The main
business rule: a new booking must not overlap an existing **confirmed**
booking for the same property. A guest may check in on the day the previous
guest checks out. The app uses SQLite, Blade views and plain CSS, so it needs
no npm or build step. Fonts load from Google Fonts and fall back to system
fonts when offline. All data is generated dummy data.

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
| Views | `resources/views/` (layout, partials for icons, properties, bookings) |
| Styles | `public/css/app.css` (design tokens at the top) |
| Migrations, factories, seeder | `database/` |
| Payment webhook | `routes/api.php`, `app/Http/Middleware/VerifyWebhookSignature.php`, `app/Http/Controllers/PaymentWebhookController.php`, `Booking::applyPaymentEvent()` |
| Tests | `tests/Feature/PropertyPagesTest.php`, `tests/Feature/BookingOverlapTest.php`, `tests/Feature/BookingInputTest.php`, `tests/Feature/PaymentWebhookTest.php` |

## How the overlap rule works

A stay covers the nights from check-in up to, but not including, check-out.
Two stays overlap when each starts before the other ends:

```
existing.check_in < new.check_out  AND  existing.check_out > new.check_in
```

Because the comparisons are strict, back-to-back stays are allowed. Only
`confirmed` bookings block dates; `pending` and `cancelled` ones do not.

## Payment webhook

`POST /api/webhooks/payments` receives events from a payment provider. A
booking plays the role of the order:

| Event type | Effect on the booking |
|---|---|
| `payment.succeeded` | becomes `confirmed`, unless its dates now clash with another confirmed booking (outcome `clash`, status unchanged) |
| `payment.failed` | becomes `cancelled` |
| anything else | recorded, no change (outcome `ignored`) |

How it stays safe:

- **Signature.** `VerifyWebhookSignature` middleware recomputes
  `HMAC-SHA256("{X-Webhook-Timestamp}.{raw body}", PAYMENTS_WEBHOOK_SECRET)`
  and compares it with `X-Webhook-Signature` using `hash_equals`. Requests
  older than 5 minutes are rejected, so a captured request can't be replayed.
  With no secret configured, every request is rejected.
- **Duplicates.** Each event is stored in `webhook_events` with a unique
  `event_id`. A repeat delivery fails that insert and gets `{"status":"duplicate"}`
  with a 200, so the provider stops retrying.
- **Logging.** Every accepted event is saved in `webhook_events` (with the
  outcome) and written to `storage/logs/laravel.log`, along with rejections.

To try it locally, add `PAYMENTS_WEBHOOK_SECRET=demo-secret` to `.env`,
restart `php artisan serve`, and send a signed event (booking 18 is a pending
booking in the seed data):

```bash
SECRET=demo-secret
BODY='{"id":"evt_demo_1","type":"payment.succeeded","data":{"booking_id":18}}'
TS=$(date +%s)
SIG=$(printf '%s.%s' "$TS" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -X POST http://127.0.0.1:8000/api/webhooks/payments \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "X-Webhook-Timestamp: $TS" -H "X-Webhook-Signature: $SIG" \
  -d "$BODY"
```
