# Database Design Baseline — MWH

This is a design baseline, not the final SQL schema. The final schema must be reviewed before migrations are created.

## Candidate Domain

### users
Core candidate/admin identity and account state.

Potential concepts:
- id
- role/type
- mobile
- status
- verified_at
- blocked_at
- created_at
- updated_at

### candidate_profiles
Personal information associated with a candidate.

### professional_details
Professional/experience information.

### job_preferences
Candidate category/role/travel/work-hour preferences.

### candidate_documents
Document metadata, secure storage reference, document type, review status and admin review metadata.

### candidate_payment_profiles
Bank/UPI payment details with restricted access.

## Master Data

### categories
Admin-managed candidate/vacancy categories.

### job_roles
Admin-managed roles/services linked to categories where required.

## Hospitality Domain

### hotels
Registered hotels managed by MWH.

### vacancies
Hotel job openings, role, openings count, payout, working hours, status and description.

## Recruitment Domain

### applications
Candidate-to-vacancy application records and status history/current status.

### jobs
Approved candidate assignment/job records created from the recruitment workflow.

## Attendance Domain

### attendance/check_ins
Check-in/check-out records, timestamps, evidence reference and approved location metadata if location tracking is finalized.

## Finance Domain

### payments
Admin-entered candidate payments, amount, date, reference/method and audit metadata.

## Administration

### admin_users
Admin identities and status.

### admin_roles / permissions
Role-based access control if multiple admin roles are required.

### audit_logs
Important admin/system actions with actor, action, entity and timestamp.

## Supporting Domain

### notifications
Candidate/admin notifications if persisted notification history is required.

### support_settings
Current support phone/contact configuration.

## Design Rules

1. Use InnoDB and appropriate UTF-8 encoding.
2. Use primary keys and foreign keys for relationships.
3. Add indexes based on actual query patterns.
4. Avoid storing duplicate derived values unless justified.
5. Use timestamps consistently.
6. Use status enums or controlled lookup values consistently; final choice should follow the API/backend implementation strategy.
7. Sensitive document/payment data requires restricted access.
8. Historical business records should generally be deactivated/soft-deleted instead of physically deleted.
9. All schema changes must be migration-based.

## Pending Design Decisions

Before writing migrations, finalize:
- Exact table/column names
- UUID vs numeric IDs
- Status values
- Application status lifecycle
- Job status lifecycle
- Payment fields/reference rules
- Document storage provider/path strategy
- OTP provider and OTP persistence strategy
- Whether GPS is mandatory for check-in
- Hotel location/geospatial requirements
- Notification persistence requirements
