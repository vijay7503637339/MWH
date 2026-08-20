# MWH Database

## Stack
- MySQL 8+
- InnoDB
- utf8mb4 / utf8mb4_unicode_ci

## Source of truth
The initial schema is in:

`database/migrations/001_initial_schema.sql`

All future schema changes must be added as new sequential migrations:

```text
002_add_x.sql
003_change_y.sql
004_add_z.sql
```

Do not edit an already-applied production migration. Create a new migration instead.

## What this schema supports

- Candidate OTP authentication and account verification
- Candidate personal/professional information
- Dynamic categories and job roles
- Candidate job preferences
- Candidate documents and admin document review
- Bank/UPI payment profiles
- Registered hotels and hotel images
- Hotel vacancies and payout configuration
- Candidate applications
- Job assignments
- Camera-evidence check-in and check-out records
- Manual candidate payments and transaction history
- Notifications
- Support contact configuration
- Admin users, roles and permissions
- Audit logging

## Security rules

1. Never store plaintext OTPs. Store a hash and expiry.
2. Never commit production credentials, API keys or secrets.
3. Bank account numbers must be encrypted at the application layer before storage.
4. Candidate documents must be stored outside the public web root or behind controlled access URLs.
5. Aadhaar/PAN/document numbers should not be logged.
6. Admin passwords must use PHP `password_hash()` / `password_verify()`.
7. Sensitive document/payment access must be permission controlled and audited.
8. Do not seed a production admin password in SQL.

## Important product rule

The Flutter client must expose only the camera for check-in evidence. The API records `check_in_capture_source = camera` and validates the submitted request. The server cannot independently prove how a device selected a file, so future anti-fraud hardening can add device attestation, signed capture metadata, GPS/geofence checks, or a server-side capture workflow if required.

## Local setup

1. Create a local MySQL database/server.
2. Run `database/migrations/001_initial_schema.sql` using a MySQL 8 client.
3. Configure the PHP backend using environment variables only.
4. Never commit `.env` or production credentials.

## Next implementation stage

The next stage is the PHP admin authentication + admin panel foundation, built against this schema. Before production deployment, the schema should be run through a migration tool and tested on a clean database and a representative staging database.
