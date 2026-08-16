# Sales Export / Import Feature — How It Works

This document explains exactly how the **Sales** export/import feature was built
in this project, so you can copy the same pattern into other projects (Laravel +
Inertia + Vue).

---

## 1. What the feature does

### Export (button on the Sales list page)
- Downloads an `.xlsx` file with **every sales row** the current user can see.
- Columns: `#` (the **id**), `Name`, `Name (AR)`, `Image url`, `Created by`,
  `Created at`, `Updated at`.
- The `#` column is the key: it lets you re-import the same file and **restore
  the exact same ids**.

### Import (separate page)
1. **Download a template** — a blank file (headers + an "Instructions" sheet)
   you fill in with your own data and import later.
2. **Download an example** — same file but with 2 sample rows so you see how to
   fill it.
3. **Upload** the file (`.xlsx`, `.xls` or `.csv`, max 5MB) and pick a strategy.
4. **Preview** — nothing is written yet. You see every parsed row, its status
   (`new` / `exists`), and you can **edit the names** or **skip** rows.
5. **Confirm** — the rows are written according to the chosen strategy, and a
   summary is shown (created / updated / skipped / cleared).

### The 4 import strategies
| Strategy             | What happens                                                                                                                                        |
|----------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|
| `update`             | Rows whose `#` id already exists are **updated**. Unknown/empty ids are **inserted** (new rows).                                                    |
| `create`             | Every row is **inserted as a brand-new record** (new auto ids). Nothing existing is touched.                                                        |
| `delete_all_then_add`| **Deletes ALL existing rows first**, then inserts every row **preserving its exact id**. Used for a full restore.                                    |
| `add_only`           | Only rows whose id **does not exist yet** are inserted. Existing ids are **skipped** (never updated, never duplicated).                              |

---

## 2. Files involved

### Backend (controllers)
| File | Purpose |
|---|---|
| `app/Http/Controllers/Admin/Sales/Export/AdminSalesExportController.php` | Streams the XLSX export (uses PhpSpreadsheet). |
| `app/Http/Controllers/Admin/Sales/Import/AdminSalesImportPageController.php` | Renders the import Inertia page. |
| `app/Http/Controllers/Admin/Sales/Import/AdminSalesImportPreviewController.php` | Parses the uploaded file, marks each row `new`/`exists`, returns JSON for the preview. |
| `app/Http/Controllers/Admin/Sales/Import/AdminSalesImportCommitController.php` | Applies the (edited) rows using the chosen strategy, in a DB transaction. |
| `app/Http/Controllers/Admin/Sales/Import/AdminSalesImportTemplateController.php` | Downloads the blank template or the example file (`?example=1`). |

### Routes
```php
// inside the sales permission group in routes/web.php
Route::get('/admin/sales/export', AdminSalesExportController::class)->name('admin.sales.export');
Route::get('/admin/sales/import/template', AdminSalesImportTemplateController::class)->name('admin.sales.import.template');
Route::get('/admin/sales/import', AdminSalesImportPageController::class)->name('admin.sales.import.page');
Route::post('/admin/sales/import/preview', AdminSalesImportPreviewController::class)->name('admin.sales.import.preview');
Route::post('/admin/sales/import/commit', AdminSalesImportCommitController::class)->name('admin.sales.import.commit');
```

### Frontend (Vue)
| File | Purpose |
|---|---|
| `resources/js/Pages/Admin/Sales/Import/SalesImportView.vue` | The 3-step page: upload → preview/edit → done. |
| `resources/js/Pages/Admin/Sales/List/SalesListView.vue` | Header now has **Export** and **Import** buttons. |

### Translations
`lang/en/admin.php` and `lang/ar/admin.php` → the `sales` array now contains all
the export/import labels (`export`, `import`, `download_template`, `strategy_*`,
`confirm_import`, …). They reach the frontend automatically through the shared
`translations.admin` prop.

---

## 3. How the pieces talk to each other

```
Sales list page ──► Export button  ──► GET  /admin/sales/export          ──► .xlsx download
                └─► Import button ──► GET  /admin/sales/import           ──► Import page (Vue)
                                      GET  /admin/sales/import/template  ──► blank template
                                      GET  /admin/sales/import/template?example=1 ──► example file

Import page:
  step 1  upload + strategy ──► POST /admin/sales/import/preview  ──► JSON rows {id, name_ar, name_en, image_url, created_by, status: new|exists, errors}
  step 2  preview + edit   (frontend edits name fields, removes rows)
  step 3  confirm ──► POST /admin/sales/import/commit  ──► JSON {strategy, created, updated, skipped, cleared, errors}
```

- The **preview** endpoint only reads the file and the DB (no writes).
- The **commit** endpoint wraps everything in `DB::beginTransaction()`; any
  per-row failure is collected and the row is skipped (it does not roll back the
  whole import).
- The image URL from the export is re-downloaded and attached to the media
  collection during import. If the URL is empty or the download fails, the row
  is still imported without an image (failures are logged, never fatal).

---

## 4. The spreadsheet contract

Both the export, the template and the parser use the **same column layout**.
The import parser finds the header row by scanning for a cell containing `#`
and reads the rows below it, so a title block / meta rows above the table are OK.

| Column        | Header        | Import behavior                                                        |
|---------------|---------------|------------------------------------------------------------------------|
| A             | `#`           | Optional row id. Empty = new row. Used by `update`/`add_only`/`delete_all_then_add`. |
| B             | `Name`        | English (or only) name — at least one name is required.                |
| C             | `Name (AR)`   | Arabic name (optional if B is filled).                                 |
| D             | `Image url`   | Optional public URL; downloaded and attached on import.                |
| E             | `Created by`  | Optional user id; used only if that user still exists, otherwise the importer. |
| F, G          | `Created at` / `Updated at` | Ignored on import (kept for information).               |

---

## 5. How to replicate this in another project

1. **Copy the pattern, not the code.** For a new model `X`:
   - `AdminXExportController` — query + `buildSpreadsheet()` (see Sales export).
   - `AdminXImportPreviewController` — parse file, return rows + status.
   - `AdminXImportCommitController` — the 4 strategies switch.
   - `AdminXImportTemplateController` — template/example download.
   - `AdminXImportPageController` — render the Inertia page.
2. **Routes**: register the 5 routes inside the model's permission group,
   **before** any `{model}` wildcard routes.
3. **Frontend**: copy `SalesImportView.vue` and adapt the column list; copy the
   Export/Import buttons from `SalesListView.vue`.
4. **Matching rows**: for your model decide how "exists" is matched. For Sales we
   matched by **id** (the exported `#` column). For models without a stable id you
   can match by a slug or by name instead — the facility import in this project
   does exactly that (`where('slug', …)` then `where('name->en', …)`).
5. **Translations**: add the same keys to your model's block in both language files.
6. **Images**: only include an image column if your model has media. Keep the
   download+attach in a `try/catch` so a bad URL never breaks the import.

---

## 6. Gotchas / notes

- `PhpSpreadsheet` is already a dependency (`phpoffice/phpspreadsheet`).
- The export is a `StreamedResponse`, so `HandleInertiaRequests::share()` never
  runs — if you read the session locale there, re-resolve it inside the
  controller (see `AdminSalesExportController`).
- The parser accepts `.csv` too (via `PhpOffice\PhpSpreadsheet\Reader\Csv`).
- `delete_all_then_add` deletes with `Sales::query()->delete()` (not
  `truncate()`), so foreign keys / soft-delete behavior stay normal, and
  inserting explicit ids re-bases the auto-increment automatically.
- Duplicate ids **inside one file** are handled: the second occurrence becomes a
  fresh auto-id row instead of failing with a unique-constraint error.
