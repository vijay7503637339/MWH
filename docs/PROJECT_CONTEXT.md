# Project Context — Maan World Hospitality

## Purpose

Maan World Hospitality (MWH) provides hospitality staffing/consultancy services. The platform is intended to help MWH manage candidates and fulfill hotel staffing vacancies.

## Core Business Flow

```text
Candidate Registration
        ↓
Admin Verification
        ↓
OTP Login
        ↓
Browse Hotels
        ↓
Browse Vacancies
        ↓
Apply
        ↓
Admin Reviews Candidate
        ↓
Candidate Assigned/Sent to Hotel
        ↓
Camera Check-In
        ↓
Check-Out
        ↓
Manual Admin Payment Entry
        ↓
Candidate Payment History
```

## User Types

### Candidate
- Register account
- Submit personal, professional, document and payment information
- Wait for admin verification
- Login using mobile OTP
- Browse hotels and vacancies
- Apply to vacancies
- View active/past jobs
- Check in and check out for assigned work
- View payment/transaction history
- Contact support

### Admin
- Authenticate into admin panel
- Verify, reject, block/unblock candidates
- Manage hotels
- Manage vacancy categories/job roles
- Create and manage vacancies
- Review applications
- Assign/send candidates to hotels
- Review attendance/check-in/check-out evidence
- Record manual payments
- View operational dashboard and reports
- Manage support contact information
- Review audit activity

## Technology Baseline

- Flutter mobile application
- PHP REST API
- MySQL database
- PHP + HTML + CSS admin panel
- Mobile OTP authentication

## Important Product Constraints

- Candidate login is mobile-number + OTP based.
- Password authentication is not part of the current candidate login design.
- Candidate accounts require admin verification before normal application access.
- Check-in evidence must be captured using the device camera; gallery selection must not be offered for check-in photos.
- Payment is manually recorded by the admin after MWH pays the candidate.
- Categories and job roles must be admin-managed rather than hard-coded.

## Development Principle

Treat this document as stable project context. Update it only when an approved product decision changes the project baseline.
