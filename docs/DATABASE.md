# Database Design — MWH

The database baseline has now been converted into the initial MySQL 8 migration:

`database/migrations/001_initial_schema.sql`

## Architecture

### Administration
- `admin_roles`
- `admin_permissions`
- `admin_role_permissions`
- `admin_users`
- `audit_logs`

### Candidate / authentication
- `users`
- `otp_codes`
- `candidate_profiles`
- `professional_details`
- `job_preferences`

### Documents / payment profile
- `candidate_documents`
- `candidate_payment_profiles`

### Master data
- `categories`
- `job_roles`

### Hospitality
- `hotels`
- `hotel_images`
- `vacancies`

### Recruitment
- `applications`
- `jobs`

### Attendance
- `attendance_records`

### Finance
- `payments`

### Supporting services
- `notifications`
- `support_settings`

## Key relationships

```text
users
 ├── candidate_profiles
 ├── professional_details
 ├── job_preferences ──> categories / job_roles
 ├── candidate_documents
 ├── candidate_payment_profiles
 ├── applications ──> vacancies ──> hotels
 ├── jobs ──> hotels / vacancies
 ├── attendance_records
 ├── payments
 └── notifications

categories
 └── job_roles

hotels
 ├── hotel_images
 └── vacancies

vacancies
 └── applications
      └── jobs
           └── attendance_records

admin_roles
 └── admin_role_permissions ──> admin_permissions
      └── admin_users
```

## Important design decisions

1. Candidate login is mobile + OTP. No candidate password is stored.
2. OTP values are stored only as hashes with expiry and attempt limits.
3. Candidate verification/blocking is represented on `users` and audited.
4. Categories and job roles are dynamic admin-managed master data.
5. A candidate can apply only once to the same vacancy (`unique vacancy_id + user_id`).
6. A job is created from an approved application and keeps a snapshot of the agreed payout.
7. Attendance supports check-in and check-out records and camera-evidence metadata.
8. GPS fields are nullable because mandatory geolocation has not yet been finalized as a business requirement.
9. Payments are manually entered by authorized admins and linked to a candidate and optionally a job.
10. Bank account numbers are intended to be application-layer encrypted before storage.
11. Candidate documents store a protected storage reference rather than public file URLs.
12. Admin access uses role-based permissions.
13. Important administrative actions are recorded in `audit_logs`.
14. Historical business records should be deactivated/soft-deleted where appropriate rather than physically deleted.

## Status values used by the application

The database intentionally uses VARCHAR status fields so business workflows can evolve without destructive schema changes. The backend must centralize allowed values.

### Candidate account
- `pending_verification`
- `verified`
- `rejected`
- `blocked`

### Document review
- `pending`
- `approved`
- `rejected`

### Hotel
- `active`
- `inactive`

### Vacancy
- `draft`
- `published`
- `paused`
- `closed`

### Application
- `applied`
- `under_review`
- `shortlisted`
- `approved`
- `rejected`
- `withdrawn`

### Job
- `assigned`
- `active`
- `completed`
- `cancelled`

## Security

- Never commit credentials or secrets.
- Never log OTPs, Aadhaar/PAN numbers, bank account numbers, or document contents.
- Store candidate documents outside the public web root or behind authenticated access.
- Use `password_hash()` / `password_verify()` for admin passwords.
- Use API authorization middleware for every protected endpoint.
- Audit sensitive admin operations.

## Next stage

The database is ready as the foundation for the **Admin Panel Phase 1: Admin Authentication + RBAC + Admin Layout + Dashboard shell**. The PHP backend should use migrations rather than manually changing production tables.
