# Build the facility export — steps for the OLD project

You are working on the **old site**. Its job is to produce a migration package
(`.zip`) that the new site imports. The new site's importer is already built and
will not change — so the package must match
`facility-migration-package-spec.md` exactly. Keep that spec open; this document
is the build order, the spec is the contract.

There are two paths. **Read step 0 first and pick one.**

---

## Step 0 — Which path applies

Run this in the old project:

```bash
ls app/Models/Facility.php app/Models/FacilityBranch.php
grep -l "InteractsWithMedia" app/Models/Facility.php
php -r 'echo extension_loaded("zip") ? "zip ok" : "ZIP MISSING";'
```

- **All three succeed** → the old site is the same Laravel codebase.
  Take **Path A** (copy the files). Roughly 30 minutes.
- **Anything is missing** (different framework, no Spatie MediaLibrary, different
  table shapes) → take **Path B** (build to the spec). Longer, but the contract
  is fully specified.

If `zip` is missing, install `ext-zip` before anything else — the package cannot
be produced without it.

---

# Path A — same codebase (copy the working exporter)

## A1. Copy four files from the new project

| From the new project | To the old project, same path |
| --- | --- |
| `app/Services/FacilityMigration/FacilityMigrationExporter.php` | same |
| `app/Http/Controllers/Admin/Facility/Migration/AdminFacilityMigrationExportController.php` | same |
| `app/Console/Commands/FacilityMigrationExport.php` | same |
| `docs/facility-migration-package-spec.md` | same (reference only) |

Do **not** copy the importer, the import controller, or the migration Vue page —
the old site does not import anything.

## A2. Add the two routes

In `routes/web.php`, inside the group that already guards the facility screens
(`permission:manage facilities|manage own facilities`), and **above** any
`/admin/facility/{facility}` route so the static segments are not swallowed:

```php
use App\Http\Controllers\Admin\Facility\Migration\AdminFacilityMigrationExportController;

Route::get('/admin/facility/migration/export', AdminFacilityMigrationExportController::class)
    ->name('admin.facility.migration.export');
Route::get('/admin/facility/migration/export/plan', [AdminFacilityMigrationExportController::class, 'plan'])
    ->name('admin.facility.migration.export.plan');
```

If you only ever run the export from the command line, these routes are optional —
the artisan command alone is enough for a one-time migration.

## A3. Reconcile the schema differences

This is the step that actually matters. The exporter reads specific columns and
relations; the old database may not have all of them. Check each row, and **delete
the corresponding line from the exporter** for anything the old site lacks —
missing data is fine (the importer treats absent keys as null), a fatal error is
not.

```bash
php artisan tinker --execute='
$f = new App\Models\Facility;
echo "fillable: ".implode(",", $f->getFillable())."\n";
echo "translatable: ".implode(",", $f->translatable ?? [])."\n";
echo "columns: ".implode(",", Schema::getColumnListing("facilities"))."\n";
echo "branch columns: ".implode(",", Schema::getColumnListing("facility_branches"))."\n";
echo "has offers table: ".(Schema::hasTable("offers") ? "yes" : "no")."\n";
echo "has facility_tag: ".(Schema::hasTable("facility_tag") ? "yes" : "no")."\n";
echo "has sales: ".(Schema::hasTable("sales") ? "yes" : "no")."\n";'
```

| If the old site does not have… | Do this in `FacilityMigrationExporter` |
| --- | --- |
| `meta_title` / `meta_description` / `meta_keywords` / `canonical_url` | Remove those keys from `facilityPayload()` |
| `sales` table or `sales_id` | Remove `sales` from `$with`, from `facilityPayload()` and from `lookupPayload()` |
| `discount_percent` | Remove that key from `facilityPayload()` |
| `offers` table | Call `build()` with `include_offers => false`, or strip the offer code |
| `facility_tag` / `tags()` | Remove `tags` from `$with`, `facilityPayload()` and `lookupPayload()` |
| `governorate_id` / `city_id` on facilities | Remove `governorate` / `city` from the facility payload (branches keep theirs) |

Then confirm the media collection names match what the old site actually uses:

```bash
php artisan tinker --execute='
echo implode("\n", DB::table("media")
  ->where("model_type", App\Models\Facility::class)
  ->distinct()->pluck("collection_name")->all());'
```

Expected: `logo`, `mobile_logo`, `image`, `mobile_image`, `og_image`, `gallery`.
Anything else, note it — the new site only renders those, so a stray collection
name imports but is never displayed.

## A4. Produce a test package first

Never start with the whole site. One facility:

```bash
php artisan facility:migration-export --slug=<some-existing-slug> \
  --output=/tmp/test-package.zip
```

Then verify it against the spec's §8 checklist:

```bash
unzip -l /tmp/test-package.zip | head -30
unzip -p /tmp/test-package.zip data/facilities.json | python3 -m json.tool | head -60
```

Look for, specifically:
- `"format": "ibusiness-medical/facility-migration"` and `"format_version": 1`
- translatable fields as locale maps with **both** `en` and `ar` — not flat strings
- `phone` as an array
- every `media[].package_path` present in the `unzip -l` listing
- `counts.media_files_missing` is `0`

## A5. Have the new site prove it

Send `/tmp/test-package.zip` to whoever runs the new site. They run:

```bash
php artisan facility:migration-import /tmp/test-package.zip --inspect
php artisan facility:migration-import /tmp/test-package.zip --mode=merge --dry-run
```

A dry run reporting the expected counts with no warnings means the contract is
met. **Do not export the full site until this passes.**

## A6. Export the real thing, in parts

A whole site with images in one zip is usually too big to move. Export in slices —
each part is a complete package the new site imports independently. The command
already does the arithmetic; just walk the part numbers up:

```bash
php artisan facility:migration-export --part=1 --per-part=25
php artisan facility:migration-export --part=2 --per-part=25
# …it prints the next command each time, and stops you when you pass the last part
```

The first run tells you how many parts there are (`Building part 1 of 12 (287
facilities match)…`), and each package lands in
`storage/app/facility-migration/` named `…-part01-of-12-<timestamp>.zip`.

`--offset` / `--limit` are there too if you want to drive the slicing yourself.

**If the parts are still too large**, split data from images:
`--no-media` gives small data-only packages, and the images move separately as a
plain zip of `storage/app/public/` — the new site accepts that with its
`--media=/path/to/unzipped/storage/app/public` option.

## A7. Hand over

For each part, send the `.zip` and tell the new site to import with
`--mode=merge`. Order does not matter. Keep the parts until the new site has
confirmed the data landed — do not delete the old site's `storage/` yet.

---

# Path B — different codebase (build to the spec)

Implement an exporter that emits exactly the archive described in
`facility-migration-package-spec.md`. Build in this order, testing each stage:

1. **Envelope + one facility, no relations.** Get `data/facilities.json` parsing
   with the right `format` / `format_version`, and one facility carrying its
   scalar columns. Verify the new site's `--inspect` reads it.
2. **Translations.** Every translatable column becomes a locale map
   (`{"en": …, "ar": …}`), with empty/null locales dropped. Spec §3.3. This is
   where most exporters go wrong — flattening to one language is silent data loss.
3. **Lookups + inline refs.** Emit the `lookups` block, and give each facility its
   `facility_type` / `governorate` / `city` / `sales` as `{id, slug, name}`
   objects. Never bare foreign keys — the new site's ids differ. Spec §3.2.
4. **Branches**, nested in their facility, `phone` as an array. Spec §3.4.
5. **Tags and offers.** Offers nest inside whichever owner they belong to; do not
   emit `offerable_type`/`offerable_id`. Spec §3.5.
6. **Media metadata**, then **media bytes** at `media/{media_id}/{file_name}`.
   Spec §4. The importer needs `collection_name`, `file_name`, `name`,
   `order_column`, `custom_properties` and `source_relative_path`.
7. **`manifest.json` + `data/media.csv`.** Optional for import to work, required
   for anyone to debug a bad migration.
8. **Slicing.** Order by `id` ascending, accept `offset`/`limit`, record
   `options.slice`, and make every part self-contained. Spec §6.

Then run the spec's §8 acceptance checklist and do A5 before exporting the site.

---

## Rules that are easy to get wrong

- **Locale maps, not strings.** `"name": "Sunrise"` loses the Arabic. It must be
  `"name": {"en": "Sunrise", "ar": "عيادة الشروق"}`.
- **Objects, not foreign keys.** `"facility_type_id": 3` is meaningless on the new
  site. Send `{"id": 3, "slug": "clinic", "name": {…}}`.
- **`sales.name` is a raw string** copied byte-for-byte, even if the model calls
  it translatable. Wrapping it in a locale map double-encodes it.
- **Empty arrays, never `null`,** for `tags` / `branches` / `offers` / `media`.
- **Stable `ORDER BY id ASC`** everywhere, or slicing will skip and duplicate rows
  between parts.
- **Media ids are not preserved** on the new site — that is expected. Do not try
  to force them.
