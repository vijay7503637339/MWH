# Admin Flow — MWH

## Dashboard

Show operational summaries such as:
- Pending candidate verifications
- Active candidates/jobs
- Open vacancies
- Recent applications
- Pending attendance review where applicable
- Recent payments

Exact KPI definitions must come from approved database/API design.

## Candidate Management

```text
Candidate List
    ↓
Candidate Detail
    ├── Personal Information
    ├── Professional Information
    ├── Documents
    ├── Payment Information (restricted)
    ├── Applications
    ├── Jobs
    └── Payment History
```

Admin actions:
- Verify
- Reject with reason
- Block
- Unblock
- View candidate history

## Hotel Management

Admin can:
- Add hotel
- Edit hotel
- Publish/unpublish or activate/deactivate
- View hotel details
- Manage vacancies under hotel

Avoid destructive deletion for records referenced by historical jobs/payments.

## Master Data

Admin manages:
- Vacancy categories
- Job/service roles

Examples are not fixed values; they are seed/sample data only.

## Vacancy Management

Admin can:
- Create vacancy
- Edit vacancy
- Publish/unpublish
- Close vacancy
- Set number of openings
- Set payout
- Set working hours
- Add description and other approved fields

## Application Management

Admin can:
- View applications
- Filter/search
- Open candidate details
- Review application
- Update application status
- Move eligible candidate to assignment/job workflow

## Job & Attendance Management

Admin can:
- View active jobs
- View past jobs
- View check-in/check-out
- Review evidence
- Resolve operational exceptions according to approved business rules

## Payment Management

Admin can:
- Select candidate/job
- Enter payment amount
- Enter payment date/reference/method as finalized
- Save payment
- View payment history

Payment records should be auditable and should not be silently overwritten.

## Support Settings

Admin can maintain the support phone number and other approved support contact information shown in the app.
