# Business Rules — MWH

These rules are the current product baseline. Do not change them during implementation without an approved requirement change.

## Candidate Account

1. Mobile number is the primary candidate login identifier.
2. Candidate login uses OTP; password login is not required in the current scope.
3. A newly registered candidate starts in `pending_verification` state.
4. Admin verification is required before the candidate can use protected job/application functionality.
5. Admin can reject, block and unblock candidates.
6. Rejection should have a reason field where the UI supports it.
7. A blocked candidate must not be able to use protected candidate functionality.

## Registration

8. Registration is multi-step and should preserve entered data when moving between steps.
9. Candidate must complete required fields and documents before final submission.
10. Category and job/service role are admin-controlled master data.
11. Payment preference is bank account or UPI.
12. Sensitive document and payment data must not be exposed in unnecessary API responses.

## Hotels and Vacancies

13. Only active/published hotels are visible to candidates.
14. Only active/published vacancies are visible to candidates.
15. A vacancy belongs to one hotel and one job/service role.
16. Vacancy opening count and payout are controlled by admin.
17. Deleting business records that have historical transactions should normally be implemented as deactivation/soft delete rather than destructive deletion.

## Applications

18. Candidate must be verified and not blocked to apply.
19. Candidate can apply to a vacancy only when the vacancy is eligible for applications.
20. Duplicate application to the same vacancy is prevented by default.
21. Application status is controlled by the backend/admin workflow.

## Jobs

22. An application may become a job/assignment only through the approved admin workflow.
23. Candidate can check in only when there is an eligible active job assignment.
24. Candidate can check out only after a valid check-in.
25. Attendance timestamps should be server-authoritative where possible.

## Check-In Evidence

26. Check-in photo must be captured using the device camera.
27. Gallery/file-picker access must not be provided for check-in evidence.
28. The backend must validate the check-in request and job eligibility; the mobile client cannot be trusted as the only enforcement layer.
29. Location capture should be implemented only after the final privacy/business requirement is approved.

## Payments

30. Candidate does not initiate payment confirmation as the source of truth.
31. Admin records the payment after MWH makes the payment.
32. Payment history must be immutable from the candidate side.
33. Financial records should be auditable.

## Data & Security

34. Aadhaar, PAN, bank account and other sensitive information require restricted access.
35. Secrets, OTP credentials and production database credentials must never be committed to Git.
36. APIs must perform authorization and validation server-side.
37. Admin actions affecting verification, jobs, attendance or payments should be auditable.
