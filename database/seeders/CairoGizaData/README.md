# Cairo and Giza Data Seeders

This folder contains seeders to import Cairo and Giza facility data from a JSON file.

## Structure

- `cairo-giza.json` - JSON file containing all facility data for both Cairo and Giza
- `GovernorateSeeder.php` - Creates Cairo and Giza governorates
- `FacilityTypeSeeder.php` - Creates facility types from categories in JSON
- `FacilitySeeder.php` - Imports facilities from JSON file (assigns to correct governorate)
- `FacilityBranchSeeder.php` - Creates facility branches from JSON data
- `CairoGizaDataSeeder.php` - Main seeder that runs all seeders in order

## JSON File Format

The `cairo-giza.json` file should follow this structure:

```json
{
  "governorate": {
    "ar": "القاهرة والجيزة",
    "en": "Cairo and Giza"
  },
  "medical_directory": [
    {
      "category": {
        "ar": "المستشفيات العامة والخاصة",
        "en": "General and Private Hospitals"
      },
      "facilities": [
        {
          "name": {
            "ar": "اسم المنشأة بالعربية",
            "en": "Facility Name in English"
          },
          "governorate": {
            "ar": "القاهرة",
            "en": "Cairo"
          },
          "address": {
            "ar": "العنوان بالعربية",
            "en": "Address in English"
          },
          "phones": ["1234567890"],
          "branches": [
            {
              "location": {
                "ar": "موقع الفرع",
                "en": "Branch Location"
              },
              "address": {
                "ar": "عنوان الفرع",
                "en": "Branch Address"
              },
              "phone": "1234567890"
            }
          ]
        }
      ]
    }
  ]
}
```

## Special Features

- **Dual Governorate Support**: Facilities can specify which governorate they belong to using the `governorate` field. If not specified, the seeder will attempt to infer from the address.
- **Governorate Inference**: If a facility doesn't have an explicit `governorate` field, the seeder checks the address for keywords like "الجيزة", "Giza", "6 أكتوبر", "الهرم", "Kit Kat" to determine if it's in Giza, otherwise defaults to Cairo.

## Usage

### Run all Cairo/Giza data seeders:
```bash
php artisan db:seed --class=Database\Seeders\CairoGizaData\CairoGizaDataSeeder
```

### Run individual seeders:
```bash
# Governorates only
php artisan db:seed --class=Database\Seeders\CairoGizaData\GovernorateSeeder

# Facility types only
php artisan db:seed --class=Database\Seeders\CairoGizaData\FacilityTypeSeeder

# Facilities from JSON file
php artisan db:seed --class=Database\Seeders\CairoGizaData\FacilitySeeder

# Branches (if applicable)
php artisan db:seed --class=Database\Seeders\CairoGizaData\FacilityBranchSeeder
```

## Notes

- Facility types are automatically created from categories in the JSON file
- Facilities are matched by slug (generated from Arabic name)
- Existing facilities are updated, new ones are created
- If a facility has multiple phones but no branches, additional branches are created for extra phones
- Every facility is ensured to have at least one branch (main branch)
- Branches can be specified as:
  - Simple strings (location names)
  - Objects with `location` (string or object with ar/en)
  - Objects with `address` (string or object with ar/en)
  - Objects with `phone` or `phones` array

