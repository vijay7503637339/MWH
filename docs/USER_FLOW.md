# Candidate User Flow — MWH

## Registration

1. Open app.
2. Start registration.
3. Complete Personal Information.
4. Complete Professional Information.
5. Upload required Documents.
6. Enter Payment Information.
7. Review all information.
8. Submit registration.
9. Account becomes `pending_verification`.
10. Candidate sees verification-pending state.

## Login

1. Enter registered mobile number.
2. Request OTP.
3. Enter OTP.
4. Backend verifies OTP and candidate account status.
5. Verified candidate enters the application.
6. Pending/rejected/blocked candidates receive the appropriate state/message.

## Vacancy Discovery

```text
Home / Hotels
    ↓
Hotel Detail
    ↓
Available Vacancies
    ↓
Vacancy Detail
    ↓
Apply
    ↓
Application Submitted
```

## Job Lifecycle

```text
Application
   ↓
Admin Review
   ↓
Selected / Assigned
   ↓
Active Job
   ↓
Check-In + Camera Photo
   ↓
Work
   ↓
Check-Out
   ↓
Admin Payment Entry
   ↓
Payment History
   ↓
Past Job
```

## Candidate Sections

- Home / hotel discovery
- Applications
- Active Jobs
- Past Jobs
- Transactions
- Profile
- Help / Support

## Error/Edge States

The mobile UI must have explicit handling for:
- OTP expired
- Invalid OTP
- Too many OTP attempts
- Account pending verification
- Account rejected
- Account blocked
- Vacancy closed
- Duplicate application
- Job not active
- Check-in already completed
- Check-out before check-in
- Camera permission denied
- Camera capture failure
- Network/server failure
