# Fixes for DMIMS Preparation

Date: 7 September 2026

> [!IMPORTANT]
> The following items directly impact the upcoming client demonstration. Please prioritize these fixes to ensure core workflows (intake, tracking, audit compliance, and master data setup) can be demonstrated smoothly.

## Summary Table

| Item | Description                                                                              | Priority | Difficulty   |
| ---- | ---------------------------------------------------------------------------------------- | -------- | ------------ |
| 1    | Box details page: Missing tabs (`Documents Inside`, `Box Movement Log`, `Box Audit Log`) | High     | Medium       |
| 2    | Document File details page: Missing tabs (`Movement Log`, `Audit Log`)                   | High     | Medium       |
| 3    | Customer -> Location: Edit location malfunction and unable to delete location            | High     | Low - Medium |
| 4    | Create Document File: `Current Box` field is mandatory and lacks scan input              | High     | Low          |

---

## Detailed Task Breakdown

### 1. Box Details Page: Missing Information Tabs
- Priority: High (Demo blocker: needed to demonstrate box inventory, transfer history, and audit compliance)
- Difficulty: Medium
- Description:
  - The Box profile page currently lacks tabbed views to inspect contained files, movement history, and audit records.
- Expected behavior:
  - Add 3 dedicated tabs to the Box view page:
    - Documents Inside:
      - Purpose: Displays all files currently stored in this box.
      - Columns: File Barcode, File Reference No, Title, Document Type, Department, Status, Date Added.
    - Box Movement Log:
      - Purpose: Displays location transfer and dispatch history.
      - Columns: Date & Time, Movement Type (e.g., Internal Move, External Dispatch, Return), From Location, To Location / Destination, Operator, Reference / Tracking No.
    - Box Audit Log:
      - Purpose: Displays record changes and file linking history for compliance.
      - Columns: Date & Time, Action (e.g., `create`, `update_metadata`, `file_linked`, `file_unlinked`), Performed By, Field Name, Old Value, New Value.

### 2. Document File Details Page: Missing Log Tabs
- Priority: High (Demo blocker: needed to show file traceability and audit trail)
- Difficulty: Medium
- Description:
  - The Document File details page currently lacks views to track file relocations and administrative changes.
- Expected behavior:
  - Add 2 dedicated tabs to the Document File view page:
    - Document Movement Log:
      - Purpose: Displays physical movements and box assignments over time.
      - Columns: Date & Time, Movement Type (e.g., Box Assigned, Box to Box Transfer, External Dispatch, Return), From Box / Location, To Box / Destination, Operator, Reference / Tracking No.
    - Document Audit Log:
      - Purpose: Tracks data edits, status changes, and box associations.
      - Columns: Date & Time, Action (e.g., `create`, `update_metadata`, `box_assigned`, `box_unlinked`, `status_change`), Performed By, Field Name, Old Value, New Value.

### 3. Customer -> Location: Edit and Delete Failures
- Priority: High (Demo blocker: prevents setting up or fixing physical storage hierarchy)
- Difficulty: Low - Medium
- Description:
  - In `Customer -> Location`, clicking the edit button for an existing location does not work properly.
  - Users are also unable to delete unwanted or wrongly created locations.
- Expected behavior:
  - Clicking "Edit" opens the edit form/modal pre-populated with existing location values, allowing updates to save successfully.
  - Provide a working delete action (with confirmation modal), preventing deletion only if active boxes/shelves are currently linked.

### 4. Create Document File: Make `Current Box` Optional & Support Barcode Scanning
- Priority: High (Easy to solve, critical for intake workflow demo)
- Difficulty: Low
- Description:
  - The `Current Box` field is currently marked as required (`*`). In actual operations, documents can be registered before being boxed.
  - Selecting a box solely via dropdown is slow for warehouse staff with handheld barcode scanners.
- Expected behavior:
  - Remove mandatory constraint on `Current Box` so files can be saved without an immediate box assignment.
  - Allow the `Current Box` input to accept barcode scanner input (auto-matching and selecting the box on enter/scan) while retaining manual dropdown search as fallback.

---

## Secondary Backlog (Post-Demo)

> [!NOTE]
> The following items improve usability but do not block the primary demo flow:

- Dropdown options require character input to show list (Issue 1).
- Location dropdown lacks hierarchical path information, e.g., `Room 1 > Area A > Shelf-A01` (Issue 2).
- When editing Box, `Current Location` field displays ID number only instead of readable location name (Issue 4).

---

## Status (tracked from 11 September 2026 onward)

This file is the original requirements doc as received; the text above is left
unmodified so it can be checked verbatim against the codebase. Status below is
appended, not interleaved, and is kept current in `CONFORMANCE_GAP_ANALYSIS.md`
(linked per item) as the authoritative running log of what changed, when, and why.

| Item | Status | Where |
| ---- | ------ | ----- |
| 1. Box details tabs | Done | `BoxResource` relation managers — Documents Inside, Movement Log, Audit Log |
| 2. Document File details tabs | Done | `DocumentFileResource` relation managers — Movement Log, Audit Log |
| 3. Location edit/delete | Done | `LocationResource`; edit persistence browser-verified |
| 4. Create Document File optional Current Box + scan | Done, then broadened | Optional `current_box_id`; global scan-to-create (§26 below) supersedes the original "scan into the Current Box field" ask with a system-wide scan-anywhere flow |

### Items raised after this file, tracked only in `CONFORMANCE_GAP_ANALYSIS.md` §26-27

Not in the original list above — raised in follow-up review discussion, not written
down anywhere else in the repo until now. All closed 11 September 2026 (§27):

1. Reprint/damage tracking on barcode labels — **Done.** "Copies" field added to
   every print action; "Reason" (required) added to Lost/Damaged, logged to
   `audit_logs`. Print count no longer inflates on preview re-render (was
   incrementing in `modalContent()`, moved to `mountUsing()`). Lost/Damaged still
   replaces the barcode by design (retiring a physically lost/damaged label is
   supposed to issue a new one) — that part was not a bug.
2. Box Transfer's location picker searches name/path text, not shelf barcodes —
   **Done.** `Location::searchByNameOrBarcode()`; global scanner still intentionally
   doesn't cover this (it ignores focused fields).
3. Capacity/destination validation — **Done.** Off-by-one on create fixed (the
   just-created record was counting against its own container's capacity); checks
   moved inside the transaction with a row lock (closes the concurrent-request
   race); destination `status`/`can_store_boxes` now enforced.
4. Reserved Product labels fall through the global scanner's unused-barcode
   redirect — **Done.**
5. `BarcodeService::registerExisting()` didn't check that an existing unassigned
   registry row's reserved type matches the record being created — **Done.**
6. `business-access` middleware isn't `isPersistent: true` — **Done.**

See `docs/CONFORMANCE_GAP_ANALYSIS.md` §27 for full investigation detail per item.
