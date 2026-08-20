# Candidate Signup Fields — MWH

## Step 1 — Personal Information

| Field | Required | Type | Notes |
|---|---|---|---|
| First Name | Yes | String | Candidate first name |
| Last Name | Yes | String | Candidate last name |
| Mobile | Yes | String | Primary login identifier; OTP |
| Date of Birth | Yes | Date | Validate sensible date/age rules after client confirmation |
| Email | No | Email | Validate when supplied |
| Gender | Yes | Enum | Controlled values |
| Full Address | Yes | String | Complete address |
| City | Yes | String | Candidate city |
| Postcode | Yes | String | Validate Indian postal code if India-only scope remains |
| Referral Code | No | String | Optional |

**Removed from current scope:** Password. Candidate login is OTP based.

## Step 2 — Professional Information

| Field | Required | Type | Notes |
|---|---|---|---|
| Category | Yes | Foreign key | Admin-managed; e.g. Kitchen Staff, Service Staff |
| Job/Service Role | Yes | Foreign key | Admin-managed; e.g. Tandoor Chef, Chinese Chef, Halwai |
| Experience | Yes | Number/structured value | Final representation to be confirmed |
| Maximum Travel Distance | Yes | Number | KM |
| Working Hours Preference | Yes | Number/enum | Final options to be confirmed |

## Step 3 — Documents

| Document | Required | Source | Notes |
|---|---|---|---|
| Selfie | Yes | Camera/gallery policy to be finalized | Identity/profile evidence |
| Aadhaar OR Driving Licence | Yes | Upload | Identity/address proof |
| PAN Card | Yes | Upload | Financial/tax identity |
| Police Verification | Yes | Upload | Verification evidence |

Each document should have status such as `pending`, `approved`, `rejected`, with admin review metadata.

## Step 4 — Payment Information

### Bank Account

- Account Holder Name — required
- Bank Name — required
- Account Number — required
- Confirm Account Number — UI-only validation, do not persist unnecessarily
- IFSC Code — required
- Branch Name — required

### UPI

- UPI ID — required when UPI is selected

## Step 5 — Review & Submit

- Show a read-only summary of all sections.
- Allow editing each section before submission.
- Validate all required fields again on final submission.
- After submission set candidate account to `pending_verification`.
- Display confirmation and expected verification status.

## Sensitive Data Rules

- Do not return full Aadhaar/PAN/bank details in list APIs.
- Mask sensitive values in admin tables where full values are not required.
- Store only data required by the approved business process.
