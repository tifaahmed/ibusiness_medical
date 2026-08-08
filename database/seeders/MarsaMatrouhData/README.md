# Marsa Matrouh Data Seeders

This folder contains seeders to import Marsa Matrouh facility data from a JSON file.

## Structure

- `marsa-matrouh.json` - JSON file containing all facility data
- `GovernorateSeeder.php` - Creates Marsa Matrouh governorate
- `FacilityTypeSeeder.php` - Creates facility types from categories in JSON
- `FacilitySeeder.php` - Imports facilities from JSON file
- `FacilityBranchSeeder.php` - Creates facility branches from JSON data
- `MarsaMatrouhDataSeeder.php` - Main seeder that runs all seeders in order

## JSON File Format

The `marsa-matrouh.json` file should follow this structure:

```json
{
  "governorate": {
    "ar": "محافظة مرسى مطروح",
    "en": "Marsa Matrouh Governorate"
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

### Run all Marsa Matrouh data seeders:
```bash
php artisan db:seed --class=Database\Seeders\MarsaMatrouhData\MarsaMatrouhDataSeeder
```

### Run individual seeders:
```bash
# Governorate only
php artisan db:seed --class=Database\Seeders\MarsaMatrouhData\GovernorateSeeder

# Facility types only
php artisan db:seed --class=Database\Seeders\MarsaMatrouhData\FacilityTypeSeeder

# Facilities from JSON file
php artisan db:seed --class=Database\Seeders\MarsaMatrouhData\FacilitySeeder

# Branches (if applicable)
php artisan db:seed --class=Database\Seeders\MarsaMatrouhData\FacilityBranchSeeder
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


