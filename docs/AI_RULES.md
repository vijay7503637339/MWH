# AI Development Rules — MWH

This file is the operating contract for AI coding tools working on this repository.

## 1. Read Before Coding

Before changing code, read the relevant sections of:

- `docs/PROJECT_CONTEXT.md`
- `docs/REQUIREMENTS.md`
- `docs/BUSINESS_RULES.md`
- `docs/SIGNUP_FIELDS.md`
- `docs/USER_FLOW.md`
- `docs/ADMIN_FLOW.md`
- `docs/API_CONTRACT.md` when API work is involved
- `docs/DATABASE.md` when database work is involved

Do not read or modify unrelated areas unless a dependency requires it.

## 2. Minimal Change Policy

- Reuse existing architecture and components.
- Prefer the smallest correct implementation.
- Do not rewrite working modules without a task-specific reason.
- Do not refactor unrelated code.
- Do not rename public classes/files/APIs without approval.
- Do not add dependencies unless necessary and justified.

## 3. Security

- Never commit secrets, API keys, OTP provider credentials, database passwords or production credentials.
- Never log OTPs, Aadhaar numbers, PAN numbers, full bank account numbers or other sensitive personal data.
- Enforce authorization on the backend, not only in Flutter.
- Treat all client input as untrusted.
- Validate uploads, file types, sizes and access permissions server-side.

## 4. Backend Rules

- Every protected endpoint must verify authentication and authorization.
- Validate all required fields server-side.
- Use parameterized queries/prepared statements.
- Return a consistent API response/error structure.
- Do not expose unnecessary sensitive fields.
- Use transactions where multiple related writes must succeed together.

## 5. Flutter Rules

- Follow the established project architecture once implementation begins.
- Keep API/network code out of UI widgets.
- Reuse common validation, networking, theme and UI components.
- Handle loading, success, empty, error and permission states.
- Camera-only check-in must not expose a gallery picker.

## 6. Database Rules

- Use migrations/versioned schema changes.
- Use foreign keys and indexes where appropriate.
- Prefer soft deletion/deactivation for historical business records.
- Never modify production schema manually without a migration.

## 7. Testing

For every feature:
1. Add focused tests for changed behavior.
2. Run relevant tests.
3. Run formatting/static analysis where applicable.
4. Fix regressions before continuing.

Do not create redundant tests merely to increase test count.

## 8. Git Discipline

After each accepted logical milestone:

```text
Inspect diff
→ Run tests
→ Review
→ Commit
→ Continue
```

Use clear commit messages such as:
- `feat: add candidate registration API`
- `feat: add hotel management`
- `fix: prevent duplicate vacancy applications`
- `docs: update signup requirements`

## 9. AI Response Rules

Keep final coding responses concise. Do not paste unchanged files or large code blocks unless explicitly requested.

Report only:
- What changed
- Files changed
- Tests/checks run
- Important assumptions
- Remaining issues

## 10. Stop Conditions

Stop and ask for clarification rather than guessing when:
- A requirement conflicts with an existing approved business rule.
- A destructive database change is required.
- A sensitive-data/security decision is ambiguous.
- A new paid/external service is required but not approved.
- The requested behavior would materially change the architecture.
