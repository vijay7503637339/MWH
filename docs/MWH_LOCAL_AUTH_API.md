# MWH Local Authentication API

MWH Local uses the same `users` identity as the existing MWH application. A chef/cooking staff account that already exists in MWH is not duplicated; the Local login API can automatically grant `mwh_local/staff` access when the existing account has a candidate profile.

## Base

`/api/local/auth/`

## Login

`POST /api/local/auth/login.php`

### UI fields

The login API matches the Figma login screen:

- `mobile` — 10 digit Indian mobile number; `+91` is also accepted.
- `password`
- `role` — `contractor` or `staff` (aliases `caterer`, `chef`, `cooking_staff` are accepted).

Example:

```json
{
  "mobile": "9876543210",
  "password": "********",
  "role": "contractor"
}
```

Successful response returns a 30-day Bearer token and the user's available Local roles.

## Signup

`POST /api/local/auth/signup.php`

### Core Figma onboarding fields

- `role` — `contractor` or `staff`
- `mobile` / `whatsapp_mobile` — active WhatsApp mobile
- `first_name`
- `last_name`
- `password`
- `email` (optional)

### Optional staff onboarding fields

- `date_of_birth` / `dob`
- `gender`
- `full_address`
- `city` / `preferred_area`
- `postcode`
- `experience_years` / `experience`
- `previous_company_name`
- `category_id`
- `job_role_id`
- `max_travel_distance_km`
- `preferred_working_hours`

### Optional contractor onboarding fields

- `business_name`
- `business_type`
- `full_address`
- `city`
- `state`
- `postcode`
- `gstin`

New accounts are created as `pending_verification`, matching the existing MWH candidate verification flow.

## Shared-account behavior

Existing MWH chefs/cooking staff remain in the existing `users` and `candidate_profiles` tables. Migration `012_local_app_access.sql` grants Local staff access to those existing users. The Local login API also has a safe legacy fallback that links a candidate-profile user to the Local staff role if the access row is missing.

## Database migration

Run:

`database/migrations/012_local_app_access.sql`

This adds:

- `user_app_access` — application/role access without duplicating users.
- `contractor_profiles` — Local contractor-specific profile data.

No production database credentials are committed.
