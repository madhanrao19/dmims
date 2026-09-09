# Demo Scenario Data

Reference for `DemoScenariosSeeder` (staging only — see `DEPLOYMENT_GUIDE.md`).
Seeds an isolated "Demo Scenarios Co" tenant so a live demo never touches
other seeded data.

## Login

| Role | Email | Password |
|---|---|---|
| Company Admin | `admin@demo.dmims.test` | `DMIMS_DEMO_PASSWORD` (set when seeding) |
| Warehouse Operator | `operator@demo.dmims.test` | same |

## Seeded records

- Locations: Demo Warehouse → Shelf A, Shelf B
- Boxes: `DEMO-BOX-A` (on Shelf A), `DEMO-BOX-B` (on Shelf B)
- Document Files (all start in `DEMO-BOX-A`): Lease Agreement, Invoice Batch, HR Record

Barcodes are generated (company code + sequence), not fixed strings — look
each one up on its record's detail page or the Barcode Registries list
before the demo, or scan straight from the printed label.

## Suggested 5-scenario script

1. **Register a file into a box** — Box Center → `DEMO-BOX-B` → Add Document
   Mode → scan any barcode not already registered (resolves as "unknown" /
   new intake).
2. **Transfer a file** — move one of the three seeded files from
   `DEMO-BOX-A` to `DEMO-BOX-B`.
3. **Dispatch a file** — Move Out a seeded file, then Return it to show the
   round trip logs correctly.
4. **Transfer a box** — move `DEMO-BOX-A` from Shelf A to Shelf B.
5. **Add a new location** — Scan Center → scan an unused barcode → quick-create
   a location under Demo Warehouse.

Re-running the seeder is safe (idempotent) if a demo run needs a clean reset
of anything not already touched.
