# Facility migration package — exporter specification

**Give this document to the AI working on the OLD project.**

The old site is the **exporter**. The new site (this codebase) is the **importer**
and is already built. The exporter's only job is to produce a `.zip` that matches
the contract below; the importer takes it from there and needs no changes.

Anything not described here is ignored on import, and anything described here but
missing degrades in a documented way — so build to the contract, not to a guess.

---

## 1. What has to travel

| Thing | Notes |
| --- | --- |
| Facilities | Every column, every locale |
| Branches | Nested inside their facility |
| Tags | Nested per facility (many-to-many) |
| Offers | Nested per facility **and** per branch (polymorphic) |
| Facility types / governorates / cities / sales reps | As a `lookups` block **and** inline on each row |
| Images | Metadata in the JSON, actual bytes in `media/` |

Audit log tables (`facility_logs`, `facility_branch_logs`) are **not** part of the
package. They stay on the old site.

---

## 2. Archive layout

```
manifest.json                  package metadata and counts
data/facilities.json           the dataset (see §3) — REQUIRED, exact path
data/media.csv                 one row per image (human/AI readable, optional)
media/{media_id}/{file_name}   the image bytes, mirroring storage/app/public
README-IMPORT.md               free-text notes (optional)
```

`data/facilities.json` at exactly that path is what the importer looks for. If it
is missing the package is rejected.

---

## 3. `data/facilities.json`

### 3.1 Envelope

```json
{
  "format": "ibusiness-medical/facility-migration",
  "format_version": 1,
  "generated_at": "2026-08-13T17:45:16+03:00",
  "source": {
    "app_name": "deiler",
    "app_url": "https://old-site.example",
    "media_disk": "public",
    "laravel_version": "12.44.0",
    "php_version": "8.4.24"
  },
  "options": {
    "include_media_files": true,
    "include_offers": true,
    "filters": [],
    "slice": { "offset": 0, "limit": 25, "total_matching": 310 }
  },
  "counts": { "facilities": 1, "branches": 2, "tags": 1, "offers": 1, "media": 12,
              "media_files_bundled": 12, "media_files_missing": 0 },
  "lookups": { "...": "see §3.2" },
  "facilities": [ "...see §3.3" ]
}
```

- `format` **must** be the literal string `ibusiness-medical/facility-migration`.
  A different value is rejected outright.
- `format_version` **must** be `1`. A higher number is rejected with "update the
  site code first".
- `options.slice` is `null` for a whole-site export; set it when exporting in
  parts (§6).
- `counts` is informational — shown to the operator before they commit.

### 3.2 `lookups`

Reference rows the facilities point at, so the new site can create any it lacks.

```json
"lookups": {
  "facility_types": [ { "id": 3, "slug": "clinic", "name": { "en": "Clinic", "ar": "عيادة" } } ],
  "governorates":   [ { "id": 2, "slug": "assiut", "name": { "en": "Assiut", "ar": "أسيوط" } } ],
  "cities":         [ { "id": 13, "slug": "manfalut", "name": { "en": "Manfalut", "ar": "منفلوط" },
                        "governorate_id": 2, "governorate_slug": "assiut" } ],
  "sales":          [ { "id": 2, "name": "Rep One" } ],
  "tags":           [ { "id": 8, "name": "Limited", "icon": "⏳", "color": "#6B7280" } ]
}
```

`cities[].governorate_slug` matters: it is how a city gets attached to the right
governorate when the importer has to create it. Without it, a city whose parent
governorate is not otherwise referenced is skipped with a warning.

### 3.3 A facility

```json
{
  "id": 3,
  "slug": "sunrise-clinic",
  "name":             { "en": "Sunrise Clinic", "ar": "عيادة الشروق" },
  "description":      { "en": "<p>Best clinic</p>", "ar": "<p>أفضل عيادة</p>" },
  "meta_title":       { "en": "Sunrise" },
  "meta_description": {},
  "meta_keywords":    {},
  "canonical_url": "https://old-site.example/sunrise",
  "discount_percent": "39.00",
  "latitude": null,
  "longitude": null,
  "created_at": "2026-08-11T23:32:27+03:00",
  "updated_at": "2026-08-11T23:32:27+03:00",

  "facility_type": { "id": 3, "slug": "clinic",  "name": { "en": "Clinic", "ar": "عيادة" } },
  "governorate":   { "id": 2, "slug": "assiut",  "name": { "en": "Assiut", "ar": "أسيوط" } },
  "city":          { "id": 13, "slug": "manfalut", "name": { "en": "Manfalut", "ar": "منفلوط" } },
  "sales":         { "id": 2, "name": "Rep One" },
  "created_by":    { "id": 1, "name": "Site Admin", "email": "admin@example.com" },

  "tags":     [ { "id": 8, "name": "Limited", "icon": "⏳", "color": "#6B7280" } ],
  "media":    [ "...see §4" ],
  "offers":   [ "...see §3.5" ],
  "branches": [ "...see §3.4" ]
}
```

Rules:

- **Translatable columns are locale maps, never flattened strings.**
  `name`, `description`, `meta_title`, `meta_description`, `meta_keywords` on a
  facility; `name`, `address` on a branch; `title`, `short_description`,
  `full_description` on an offer. Emit every locale present. Drop keys whose value
  is `null` or `""` — an all-empty map becomes `{}` (or `[]`; both are read as
  "no value" and restored as a SQL `NULL`).
- **Related rows are objects with `id` + `slug` + `name`, not bare ids.** The new
  database has different id sequences, so the importer matches on `slug` first,
  then on `name` in any locale, and creates the row if neither hits. `id` is
  carried for traceability only.
- `sales.name` is a **plain string**, copied byte-for-byte. Do not wrap it in a
  locale map even if the model calls it translatable.
- `created_by` is matched on **email**. No matching user on the new site means the
  facility is imported with `created_by = null` — not an error.
- `slug` is preserved when free on the target. If something else already owns it,
  the importer keeps its own generated slug and records a warning.
- `created_at` / `updated_at` are restored as given. Use ISO-8601.
- Decimals may be strings (`"39.00"`) or numbers — both are accepted.

### 3.4 A branch

```json
{
  "id": 7,
  "slug": "sunrise-clinic-main-branch",
  "name":    { "en": "Main Branch", "ar": "الفرع الرئيسي" },
  "address": { "en": "12 Test St", "ar": "١٢ شارع تجريبي" },
  "phone": ["0100000000", "0111111111"],
  "latitude": "30.1000000",
  "longitude": "31.2000000",
  "created_at": "...", "updated_at": "...",
  "governorate": { "id": 2, "slug": "assiut", "name": { "...": "..." } },
  "city":        { "id": 13, "slug": "manfalut", "name": { "...": "..." } },
  "created_by":  { "id": 1, "name": "...", "email": "..." },
  "offers": []
}
```

`phone` is an **array of strings**. A single comma/semicolon/pipe-separated string
is also accepted and split, but the array is preferred.

### 3.5 An offer

Offers hang off a facility *or* a branch — put each one inside its owner's
`offers` array. The importer sets `offerable_type`/`offerable_id` itself, so do
not send them.

```json
{
  "id": 1,
  "slug": "summer-deal",
  "title":             { "en": "Summer deal", "ar": "عرض الصيف" },
  "short_description": { "en": "...", "ar": "..." },
  "full_description":  { "en": "<p>...</p>", "ar": "<p>...</p>" },
  "phone": "455757575",
  "price": "10.00",
  "old_price": "5.00",
  "created_at": "...", "updated_at": "...",
  "media": [ "...see §4" ]
}
```

---

## 4. Images

### 4.1 Metadata — one entry per media row

```json
{
  "id": 28,
  "uuid": "d889b5fb-e9de-4916-8c80-dc871b3de61e",
  "collection_name": "logo",
  "name": "WhatsApp Image 2026-08-10 at 5.51.07 PM (1)",
  "file_name": "WhatsApp-Image-2026-08-10-at-5.51.07-PM-(1).jpeg",
  "mime_type": "image/jpeg",
  "size": 93634,
  "order_column": 1,
  "disk": "public",
  "conversions_disk": "public",
  "manipulations": {},
  "custom_properties": {},
  "generated_conversions": {},
  "responsive_images": {},
  "source_relative_path": "28/WhatsApp-Image-2026-08-10-at-5.51.07-PM-(1).jpeg",
  "package_path": "media/28/WhatsApp-Image-2026-08-10-at-5.51.07-PM-(1).jpeg",
  "public_url": "https://old-site.example/storage/28/...jpeg",
  "sha256": "13082bac...",
  "file_available": true
}
```

The fields the importer actually needs: `collection_name`, `file_name`, `name`,
`order_column`, `custom_properties`, and **one of** `source_relative_path` /
`package_path`. The rest is for verification and eyeballing.

`collection_name` must be one of the collections the app uses, or the image lands
somewhere nothing reads:

| Owner | Collections |
| --- | --- |
| Facility | `logo`, `mobile_logo`, `image`, `mobile_image`, `og_image`, `gallery` |
| Offer | `image`, `mobile_image` (whatever the old site uses) |

`gallery` holds many images — order them with `order_column`.

### 4.2 Bytes

Put each file at `media/{media_id}/{file_name}` inside the zip — the same shape as
`storage/app/public`. Copying the whole `{media_id}/` directory is fine and
preferred; extra files in it are harmless.

The importer locates a file by trying, in order:
`{media_root}/{source_relative_path}`, then `{media_root}/{id}/{file_name}`, then
`{media_root}/{file_name}`. A file it cannot find is skipped with a warning — the
facility still imports, just without that image.

**Media ids are not preserved.** The importer re-adds each file through the media
library, so rows get fresh ids and fresh paths on the new site. Old
`/storage/28/logo.jpg` URLs will not work afterwards, by design.

### 4.3 Images shipped separately

If the zip carries no `media/` directory, set
`options.include_media_files` to `false`. The operator then unzips the old
`storage/app/public` somewhere on the new server and points the importer at it —
the `source_relative_path` values are what make that work, so they must be
correct even when the bytes are absent.

---

## 5. `manifest.json` and `data/media.csv`

`manifest.json` repeats `format`, `format_version`, `generated_at`, `source`,
`options` and `counts` from the envelope, plus an `entries` map naming the paths.
It exists so a human (or a script) can see what a package holds without parsing
the whole dataset.

`data/media.csv` has one row per image with these columns:

```
media_id, owner_kind, owner_slug, model_type, model_id, collection, file_name,
mime_type, size_bytes, path_inside_zip, restore_to, sha256, file_available
```

Both are optional for the import to succeed, but produce them — they are what
makes a failed migration diagnosable.

---

## 6. Export in parts (required for a big site)

One zip holding every facility and every image is often too large to download or
upload in one go. The exporter must therefore support slicing:

- Order facilities by `id` **ascending, always** — a stable order is what makes
  slicing safe.
- Accept `offset` and `limit` (or `part` + `per_part`, `part` being 1-based).
- Record what was sliced in `options.slice`:
  `{"offset": 50, "limit": 25, "total_matching": 310}`.
- Provide a "plan" call that answers *how many facilities match* and therefore
  *how many parts* a given part size needs, so the UI can render one download
  button per part.

Each part must be a **complete, self-contained package**: its own envelope, its
own `lookups` covering the rows its facilities reference, and its own `media/`
files. Parts are imported independently, in any order, in `merge` mode.

Filters worth supporting alongside slicing: `search` (name/slug), `slug` (single
facility), `facility_type_id`, `governorate_id`, `sales_id`, `created_from`,
`created_to`.

---

## 7. How the new site consumes this

For reference — the exporter does not need to implement any of it:

- Admin → Facilities → **Migration** → Import tab: upload the zip (or drop it in
  `storage/app/facility-migration/` over FTP and type the filename), pick a mode,
  and the browser steps through the import a few facilities per request.
- CLI: `php artisan facility:migration-import package.zip --mode=fresh --dry-run`
- Modes: `merge` (match by slug, update in place, insert the rest) and `fresh`
  (delete all existing facilities and their images first).
- Re-importing the same package is idempotent — media collections present in the
  package are cleared before being re-added, so images do not stack up.

---

## 8. Acceptance checklist

Build the exporter, then prove it:

1. `data/facilities.json` parses, and `format` / `format_version` are exactly
   `"ibusiness-medical/facility-migration"` / `1`.
2. A facility with Arabic **and** English content has **both** locales in every
   translatable field — open the JSON and look.
3. `phone` is an array; `tags`, `branches`, `offers`, `media` are arrays (empty
   arrays, never `null`, when there is nothing).
4. Every `media[].package_path` exists inside the zip, and its sha256 matches
   `media[].sha256`.
5. Facility, branch and offer counts in `counts` match the array lengths.
6. Exporting with `per_part=1` on a site with 3 facilities yields 3 packages, each
   with exactly one facility and only the lookups that facility needs.
7. Round trip: hand a package to the new site, run
   `php artisan facility:migration-import pkg.zip --mode=fresh --dry-run`, and
   confirm it reports the expected counts with no warnings.
