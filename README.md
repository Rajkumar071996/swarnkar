# GoldScore (SwarnaScore)

A consent-based credit score and shared credit ledger for retail jewellers — CIBIL for the
jewellery trade. Indian jewellers extend enormous amounts of informal credit and almost none
of it reaches a credit bureau, so each shop can only see its own book.

The problem in one sentence: a customer takes ₹50,000 of gold on credit from Mahalaxmi
Jewellers, then walks into Swarnkar Jewellers and asks for ₹50,000 more. Swarnkar has no way
to know. GoldScore closes that gap. The owner types a PAN or mobile number, the customer
approves with an OTP, and the screen shows a 300–900 score **and** exactly how much the
customer already owes across the network.

This repository is the walking skeleton: real tenancy, a real ledger, and a scoring engine
that computes from that ledger rather than from stored numbers.

## Requirements

| Requirement | Version used here | Notes |
| --- | --- | --- |
| PHP | 8.4 (Homebrew) | Laravel 13 needs 8.3+. XAMPP's bundled PHP 8.2 will not run this app. |
| MariaDB / MySQL | MariaDB 10.4 (XAMPP) | Reached over TCP on `127.0.0.1:3306`. |
| Node | 20+ | Only for building the Bootstrap 5 assets. |
| Composer | 2.x | |

Because XAMPP's PHP is too old, the app is served with `php artisan serve` on Homebrew PHP
rather than through XAMPP Apache. XAMPP is still used for the database.

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the schema and set the connection in `.env`:

```bash
mysql -h 127.0.0.1 -u root -e "CREATE DATABASE IF NOT EXISTS swarnkar CHARACTER SET utf8mb4"
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=swarnkar
DB_USERNAME=root
DB_PASSWORD=
```

Two settings are specific to this app:

```dotenv
# Deterministic key behind the PAN/Aadhaar lookup hashes. Rotating it orphans every
# existing hash, so treat it as permanent once real data exists.
GOLDSCORE_BLIND_INDEX_KEY=base64:...

# The static driver always issues 9999 and writes delivery to storage/logs/otp.log.
GOLDSCORE_OTP_DRIVER=static
GOLDSCORE_OTP_STATIC_CODE=9999
```

Generate a blind index key with `php artisan key:generate --show` and paste the value.

Then build the database and start the app:

```bash
php artisan migrate --seed
npm run build          # or `npm run dev` while working on the UI
php artisan serve
```

Open http://127.0.0.1:8000 and sign in:

| Email | Password | Role | Store |
| --- | --- | --- | --- |
| `owner@swarnkar.test` | `password` | Owner, full access | Swarnkar Jewellers |
| `staff@swarnkar.test` | `password` | Staff: read scores, take payments | Swarnkar Jewellers |
| `karigar@swarnkar.test` | `password` | Goldsmith Manager, same rights as staff | Swarnkar Jewellers |
| `owner@mahalaxmi.test` | `password` | Owner of a second store | Mahalaxmi Jewellers |

Sign in as the second store's owner to see tenancy working: the same customers appear, but
none of Swarnkar's ledger does.

The OTP at the consent step is always `9999` while the static driver is enabled.

## What the demo data contains

The seeder builds two stores and twenty-one customers, each with a real transaction history.
No score is written directly — every number on screen is derived from the ledger by the
engine.

Two customers are worth looking up first:

- **Rajesh Kumar** (`9829100001`) is the profile from the product mock: three cleared credit
  accounts with one slip, scoring around 785 in the green band.
- **Suresh Agarwal** (`9829100021`) is the scenario the product exists for. His record at
  Swarnkar is spotless and he scores 900, but he is carrying ₹50,000 of unpaid credit at the
  other store. It is not even overdue yet, so nothing in the score itself hints at it — only
  the exposure panel on the report shows it.

The set covers every band deliberately: strong payers in the high 800s, chronically-late
payers in the 650–740 range, write-offs and verified fraud reports at the floor, and three
customers with no completed obligations who come back **unscored** rather than 300.

## How the score works

Each component produces a ratio between 0 and 1. The weighted average maps onto the range as
`300 + 600 × W`.

| Component | Weight | What earns full credit |
| --- | --- | --- |
| Udhar khata settlement | 60% | Cleared by the due date. Then 0.7 under 30 days late, 0.4 under 60, 0.1 beyond, 0 if still open past 60. |
| Pledged gold loan repayment | 20% | Closed on time. Renewed and auctioned score progressively worse. |
| Merchant default reports | 20% | No verified flags. Each one deducts by severity and decays after 24 months. |

Observations are weighted by amount as well as recency: clearing a ten-lakh account says far
more than clearing a two-thousand-rupee one.

Three decisions are worth knowing about:

**Thin files are not punished.** A customer with credit history but no gold loan is not scored
as a zero on the loan component — the weights are renormalised across only the components
that have data. A customer with no history at all is `UNSCORED`, never 300. The distinction
matters: 300 says "this person defaults", unscored says "we do not know yet".

**Outstanding credit is not the same as bad credit.** Money that is owed but not yet due
carries no scoring signal at all — the customer has done nothing wrong. It is still deducted
from the recommended limit, so the figure on screen is headroom to lend today rather than a
total the customer may already have used up elsewhere.

**Recent behaviour outweighs old behaviour.** Every observation is weighted by a decay that
halves every 18 months, so a customer who has cleaned up their record climbs, and reputation
earned three years ago does not carry forever.

Bands follow the product spec: 750+ green, 650–749 yellow, below 650 red. Every weight,
threshold, grace period, and credit-limit multiplier lives in `config/goldscore.php` — the
engine has no magic numbers in it.

Scores are recomputed on ledger events and written to `score_snapshots`, so a lookup is a
single indexed read rather than a live aggregation. `php artisan goldscore:recompute` backfills
after a config change.

## Privacy and consent

The score describes a person, so it is gated on that person's consent rather than the
shopkeeper's curiosity.

- No score is released without a verified OTP consent, on the web and on the API alike. A grant
  is valid for 30 minutes and is scoped to the store that took it: consent given to one
  jeweller does not travel to the shop next door.
- PAN and Aadhaar are AES-256 encrypted. Encrypted columns cannot be searched, so each is paired
  with a deterministic HMAC-SHA256 blind index that supports exact-match lookup without storing
  anything readable. Only the Aadhaar hash and last four digits are ever persisted — never the
  full number.
- Cross-store output anonymises the other merchant to a city and state label, so the network
  shows that ₹50,000 is owed to "a jeweller in Ajmer" without exposing which competitor it is
  or what was bought.
- The khata screen shows a store's own book freely but keeps network exposure behind the same
  consent gate as the score, so the gate cannot be walked around by opening a different page.
- Searches, consent grants, score views, and every ledger movement are written to `audit_logs`.

## Tenancy and roles

Ledger rows carry both a `store_id` and a `customer_id`. A global scope means a store only ever
sees its own ledger, while scoring deliberately reads across all stores — that shared history is
the entire point of the product.

| | Owner | Staff |
| --- | --- | --- |
| View scores, customers and khatas | yes | yes |
| Record payments | yes | yes |
| Issue new credit, write off, manage staff, report a default | yes | no |

## The API

`/api/v1`, token-authenticated with Sanctum. This is the contract the Flutter client will be
built against, and `tests/Feature/Api/ApiContractTest.php` pins its shape.

```
POST   /api/v1/auth/login                 → { token, user }
GET    /api/v1/auth/me
POST   /api/v1/auth/logout

POST   /api/v1/lookup/search              → masked matches
POST   /api/v1/lookup/{customer}/consent  → issues the OTP
POST   /api/v1/lookup/{customer}/verify   → opens the consent window
GET    /api/v1/lookup/{customer}/score    → 403 with consent_required until verified;
                                            on success returns score + network exposure

GET|POST  /api/v1/customers
GET       /api/v1/customers/{customer}/exposure  → consent-gated cross-store position

GET       /api/v1/khata                    → one row per customer account
GET       /api/v1/khata/{customer}         → that account's entries and totals

GET|POST  /api/v1/udhaars
POST      /api/v1/udhaars/{udhaar}/payments
```

```bash
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H 'Accept: application/json' \
  -d 'email=owner@swarnkar.test&password=password&device_name=cli' | jq -r .token)

curl -s -X POST http://127.0.0.1:8000/api/v1/lookup/search \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -d 'q=9829100001'
```

## Tests

```bash
php artisan test
```

The suite runs against an in-memory SQLite database and covers the scoring engine, the consent
gate, role permissions, store isolation, the khata ledger and its exposure calculations,
encrypted identity lookup, and the API contract.

## Layout

```
app/
  Enums/                   roles, risk bands, ledger and consent states
  Models/                  Eloquent models; Concerns/BelongsToStore applies tenancy
  Policies/                owner versus staff permissions
  Services/
    Scoring/               the engine: one calculator per component, plus snapshots
    ConsentService.php     OTP issue, verify, and grant windows
    CustomerDirectory.php  identity resolution across stores
    UdhaarLedger.php       credit, payments, aging
    CreditExposure.php     what is owed, and to whom, across the network
  Support/
    BlindIndex.php         the searchable-PII hashes
    Otp/                   pluggable delivery; swap StaticOtpChannel for an SMS provider
config/goldscore.php       every weight, threshold, and multiplier
```

## Not in this skeleton

Gold loans have no UI yet: the table, factory and scoring component exist and the seeder
populates them, but loans are not issued through the app. The Karigar/B2B registry, a
cross-store defaulter browsing UI, real SMS and WhatsApp delivery, payment gateway
integration, offline sync, and multi-language support are all deferred.

There is no chit/scheme module. It was built and then removed once the product focus settled
on shared credit exposure, which is what jewellers actually cannot see today.

Swapping the fixed OTP for a live SMS provider is one class: implement `OtpChannel`, register
it in `AppServiceProvider`, and point `GOLDSCORE_OTP_DRIVER` at it. Nothing else changes.
