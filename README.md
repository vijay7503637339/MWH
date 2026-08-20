# Maan World Hospitality (MWH)

A candidate staffing and hospitality workforce management platform for Maan World Hospitality, developed by WebStripe Technologies.

## Project

The platform connects hospitality candidates with registered hotels and their vacancies. Candidates register through a multi-step onboarding flow, are verified by an administrator, discover vacancies, apply for jobs, record camera-based check-in/check-out, and view job and payment history.

## Technology

- Mobile App: Flutter
- Backend/API: PHP REST API
- Admin Panel: PHP + HTML + CSS
- Database: MySQL
- Authentication: Mobile OTP

## Repository Structure

```text
MWH/
├── mobile/       # Flutter candidate application
├── backend/      # PHP REST API
├── admin/        # Web administration panel
├── database/     # Schema, migrations and seed data
└── docs/         # Product, architecture and AI development documentation
```

## Development Rules

1. Requirements and business rules are documented before implementation.
2. Database design is approved before production schema changes.
3. API contracts are treated as the boundary between Flutter and backend.
4. Existing code must be reused where practical; avoid unnecessary rewrites.
5. Every feature must be validated, tested and committed before moving to the next dependent feature.
6. Secrets and production credentials must never be committed.

## Client

**Maan World Hospitality**

## Development Partner

**WebStripe Technologies**

Phone: +91 80768 95035  
Email: info@webstripetechnologies.com  
Website: https://webstripetechnologies.com/

## Status

**Phase:** Project foundation and requirements definition.
