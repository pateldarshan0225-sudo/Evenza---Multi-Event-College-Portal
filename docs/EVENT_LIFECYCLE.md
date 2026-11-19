# Event and pass consistency

All lifecycle decisions use Asia/Kolkata. Published events become Completed at the end timestamp when read; database statuses are not rewritten. A missing end time means end of the listed day. Public lists and category/college counts exclude ended events.

Registration status (pending/approved/cancelled) remains independent of event status. Passes display Expired after event completion or their explicit expires_at; cancelled registrations/events invalidate passes. Pending/unpublished registrations cannot show an active pass. Historical check-in and payment records remain intact.

Registration POSTs require a session CSRF token and lock the event before checking availability, account eligibility and duplicates, including team membership. Free events issue a pass transactionally. Paid events await organizer approval; registration clicks no longer fabricate paid UPI transactions. Approval issues a missing pass once; cancellation invalidates it through shared eligibility rules. Approval does not fabricate a payment. An actual payment gateway is not implemented here.

Payments are summed from recorded paid rows before joins. Multiple payments and old duplicate pass rows cannot multiply registration cards or revenue. Student, Organizer and Admin registration views use the same pass status policy. Existing incorrect historical payment records are intentionally untouched.

Open pages refresh at the next deadline/end/expiry boundary. Refresh waits if a form has been edited, a modal is open or the page is hidden. The next server request always computes current eligibility even if the page was open earlier. Publishing an ended event through quick status controls is rejected; dates must be edited first.

Validation: lifecycle boundary/state tests; read-only SQL/PHP agreement; unique registration joins; rejected ended-registration/approval and cross-college requests. Five table fingerprints matched before/after rejection tests. No successful registration, payment, approval, cancellation or schema change was executed against the user's database. Successful writes need staging verification with disposable records.
