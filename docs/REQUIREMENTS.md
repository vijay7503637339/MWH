# Functional Requirements — MWH

## 1. Candidate Authentication

- Candidate enters registered mobile number.
- System sends OTP.
- Candidate verifies OTP.
- Account status is checked before granting normal application access.
- Pending/rejected/blocked states must be handled explicitly.

## 2. Candidate Registration

### Step 1 — Personal Information

- First name — required
- Last name — required
- Mobile — required and OTP-capable
- Date of birth — required
- Email — optional
- Gender — required
- Full address — required
- City — required
- Postcode — required
- Referral code — optional

### Step 2 — Professional Information

- Category — required, admin-managed
- Job/service role — required, admin-managed
- Experience — required
- Preferred maximum travel distance — required
- Working-hours preference — required

### Step 3 — Documents

- Selfie
- Aadhaar card or Driving Licence
- PAN card
- Police verification document

Document upload must support validation, preview and replacement before final submission. Admin must be able to review document status.

### Step 4 — Payment Information

Candidate chooses either:

**Bank Account**
- Account holder name
- Bank name
- Account number
- Confirm account number
- IFSC code
- Branch name

**UPI**
- UPI ID

### Step 5 — Review & Submit

Candidate reviews all entered information before final submission.

After submission, the account remains pending until admin verification.

## 3. Hotels

Admin can add, edit, publish/unpublish and delete/deactivate hotels. Candidate can view active/published hotels.

Hotel should support relevant business information such as name, address/location, description, images and contact information as finalized during detailed UI/database design.

## 4. Vacancies

Admin can create and manage vacancies under a hotel.

Minimum vacancy concepts:
- Hotel
- Category
- Job role
- Number of openings
- Payout
- Working hours
- Job description
- Status
- Created/updated timestamps

Candidate can view active vacancies and vacancy details.

## 5. Applications

Candidate can apply to a vacancy.

The system must prevent duplicate applications to the same vacancy unless an approved business rule later permits re-application.

Admin can review applications and update application status.

## 6. Jobs / Assignments

Once a candidate is selected/sent to a hotel, the system records the assignment as a job.

Candidate can see:
- Active jobs
- Past jobs
- Job details
- Hotel
- Vacancy/role
- Job status

## 7. Attendance

Candidate can check in only for an eligible active job.

Check-in must:
- Capture current time from the server where practical
- Capture GPS/location where permitted and finalized
- Require a photo captured directly by the camera
- Not expose gallery selection
- Store check-in evidence for admin review

Candidate can check out for an eligible active job.

## 8. Payments

Admin manually records payment after paying the candidate.

Candidate can view transaction history including amount, payment date, job/reference and payment status as finalized in the API contract.

## 9. Support

Candidate can access a Help/Support section containing the current MWH support contact details.

## 10. Admin Panel

Required areas:
- Dashboard
- Candidates/users
- Candidate verification
- Hotels
- Categories
- Job roles/services
- Vacancies
- Applications
- Jobs/assignments
- Attendance
- Payments
- Support settings
- Admin users/roles
- Audit logs

## 11. Notifications

The detailed notification mechanism will be finalized before implementation. Candidate-facing notification events may include verification status, application status, assignment, check-in/check-out and payment updates.

## 12. Non-Functional Requirements

- Secure authentication and authorization
- Server-side validation for every sensitive operation
- Secure document storage/access
- Auditability of admin actions
- Consistent API error format
- Production-safe logging without sensitive personal/financial data
- Responsive and usable admin panel
- Maintainable Flutter architecture
- Database migrations/versioning
