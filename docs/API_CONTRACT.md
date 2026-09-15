# API Contract Baseline — MWH

This file defines the initial API surface. Request/response schemas must be finalized before feature implementation. Do not invent API behavior in Flutter when a contract is not defined.

## Base

```text
/api/v1
```

## Authentication

```text
POST /auth/send-otp
POST /auth/verify-otp
POST /auth/resend-otp
POST /auth/logout
```

## Registration

```text
POST /candidates/registration
PUT  /candidates/registration/personal
PUT  /candidates/registration/professional
PUT  /candidates/registration/documents
PUT  /candidates/registration/payment
POST /candidates/registration/submit
GET  /candidates/me
```

The exact endpoint granularity may be simplified if the approved implementation uses one secure registration workflow. The API must preserve the multi-step business behavior.

## Candidate Discovery

```text
GET /hotels
GET /hotels/{hotelId}
GET /hotels/{hotelId}/vacancies
GET /vacancies/{vacancyId}
POST /vacancies/{vacancyId}/apply
GET /applications
GET /applications/{applicationId}
```

## Jobs & Attendance

```text
GET  /jobs/active
GET  /jobs/past
GET  /jobs/{jobId}
POST /jobs/{jobId}/check-in
POST /jobs/{jobId}/check-out
```

Check-in must support camera-captured evidence and must be validated against an eligible job on the backend.

## Payments

```text
GET /payments
GET /payments/{paymentId}
```

Payment creation is an admin operation and will be defined under the protected admin API surface.

## Notifications

```text
GET  /notifications/list.php
POST /notifications/list.php
POST /notifications/register_token.php
```

`POST /notifications/register_token.php` requires the candidate Bearer token and accepts:

```json
{
  "token": "<FCM device token>",
  "platform": "android",
  "language": "en"
}
```

The backend upserts the FCM device token for the authenticated candidate. The admin notification screen persists the in-app notification and sends the same title/body through Firebase Cloud Messaging to all registered devices for the selected candidate(s).

## Support

```text
GET /support
```

## Admin API Areas

The final admin endpoints should cover:

```text
/admin/auth/*
/admin/candidates/*
/admin/categories/*
/admin/job-roles/*
/admin/hotels/*
/admin/vacancies/*
/admin/applications/*
/admin/jobs/*
/admin/attendance/*
/admin/payments/*
/admin/support/*
/admin/admin-users/*
/admin/audit-logs/*
```

## Response Standard

Final response envelope should follow one consistent structure, for example:

```json
{
  "success": true,
  "message": "Human-readable message",
  "data": {},
  "errors": []
}
```

Error responses must not expose stack traces, SQL errors, secrets or sensitive personal information.

## API Design Rules

- HTTPS only in production.
- Authentication token/session mechanism must be finalized before implementation.
- Authorization is enforced server-side.
- Validate every request.
- Paginate list endpoints.
- Use stable IDs and documented status values.
- Do not return sensitive fields unless the endpoint explicitly requires them.
- Document upload endpoints must enforce file validation and access control.
- Firebase service-account credentials must never be committed to the repository.
