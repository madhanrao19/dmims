# Full Website QA Verification with Playwright

Target: $ARGUMENTS

If no target is given, QA the whole site.

Run full website QA using Playwright like a real QA tester.

Test:

1. Public pages
2. Login/logout
3. Forgot password
4. Registration, if available
5. Dashboard
6. Menus
7. Forms
8. Create/edit/delete
9. Search/filter/pagination
10. Upload/download
11. Mobile view
12. Desktop view
13. Console errors
14. Network errors
15. Broken links
16. Restricted URLs
17. Role-based permissions

Create safe local sample users for every role found in the system.

Test each role:

1. Login works
2. Correct dashboard appears
3. Correct menus appear
4. Restricted pages are blocked
5. CRUD permissions work
6. User cannot access another user's private data
7. Logout works

If bugs are found:

1. Capture issue
2. Find root cause
3. Fix safely
4. Re-run failed test
5. Re-run regression checks

Final report must include:

- Roles tested
- Tests run
- Bugs found
- Bugs fixed
- Remaining bugs
- Screenshots/traces
- Final pass/fail
