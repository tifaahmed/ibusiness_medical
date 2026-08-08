# Alexandria Data Seeders

This folder contains seeders to import Alexandria facility data from a JSON file.

## Structure

- `alexandria.json` - JSON file containing all facility data
- `GovernorateSeeder.php` - Creates Alexandria governorate
- `FacilityTypeSeeder.php` - Creates facility types from categories in JSON
- `FacilitySeeder.php` - Imports facilities from JSON file
- `FacilityBranchSeeder.php` - Creates facility branches from JSON data
- `AlexandriaDataSeeder.php` - Main seeder that runs all seeders in order

## JSON File Format

The `alexandria.json` file should follow this structure:

```json
{
  "governorate": {
    "ar": "محافظة الإسكندرية",
    "en": "Alexandria Governorate"
  },
  "medical_directory": [
    {
      "category": {
        "ar": "المستشفيات",
        "en": "Hospitals"
      },
      "facilities": [
        {
          "name": {
            "ar": "اسم المنشأة بالعربية",
            "en": "Facility Name in English"
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
              "phone": "1234567890"
            }
          ]
        }
      ]
    }
  ]
}
```

## Usage

### Run all Alexandria data seeders:
```bash
php artisan db:seed --class=Database\Seeders\AlexandriaData\AlexandriaDataSeeder
```

### Run individual seeders:
```bash
# Governorate only
php artisan db:seed --class=Database\Seeders\AlexandriaData\GovernorateSeeder

# Facility types only
php artisan db:seed --class=Database\Seeders\AlexandriaData\FacilityTypeSeeder

# Facilities from JSON file
php artisan db:seed --class=Database\Seeders\AlexandriaData\FacilitySeeder

# Branches (if applicable)
php artisan db:seed --class=Database\Seeders\AlexandriaData\FacilityBranchSeeder
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


