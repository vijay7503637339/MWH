# MWH PHP Backend

PHP REST API foundation for Maan World Hospitality.

## Planned structure

```text
backend/
├── config/
├── controllers/
├── middleware/
├── models/
├── routes/
├── services/
├── validators/
├── public/
│   └── index.php
└── README.md
```

## Runtime

- PHP 8.1+ recommended
- MySQL 8.x
- PDO with prepared statements
- JSON REST API

## Security rules

- Never commit production database credentials.
- Use environment/server configuration for secrets.
- Passwords, when used for admin authentication, must use `password_hash()` and `password_verify()`.
- OTP values must be stored hashed, never as plaintext.
- Use prepared SQL statements only.
- Validate and authorize every admin action.
- Do not expose raw PHP/MySQL errors to API clients.
- Keep private uploads outside the public web root where possible.

## First milestone

Implement the database connection, common JSON response helper, CORS policy, API routing foundation, and `GET /api/v1/health` database health check.
