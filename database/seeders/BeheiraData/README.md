# Beheira Data Seeders

This folder contains seeders for importing medical facilities data for Beheira governorate from JSON format.

## Structure

- `GovernorateSeeder.php` - Creates Beheira governorate
- `FacilityTypeSeeder.php` - Creates facility types from categories in JSON
- `FacilitySeeder.php` - Creates facilities from JSON data
- `FacilityBranchSeeder.php` - Creates branches for facilities (handles branches array, location objects, hotline, and multiple phones)
- `BeheiraDataSeeder.php` - Main seeder that runs all seeders in correct order
- `beheira.json` - JSON data file containing all facility information in nested associated array format

## JSON Structure

The JSON file follows this structure with nested associated arrays for easy editing:

```json
{
  "governorate": {
    "ar": "البحيرة",
    "en": "Beheira"
  },
  "medical_directory": [
    {
      "category": { "ar": "المستشفيات", "en": "Hospitals" },
      "facilities": [
        {
          "name": { "ar": "Facility Name AR", "en": "Facility Name EN" },
          "address": { "ar": "Address AR", "en": "Address EN" },
          "phones": ["phone1", "phone2"],
          "hotline": "hotline_number",
          "branches": [
            {
              "location": { "ar": "Branch location AR", "en": "Branch location EN" },
              "phone": "branch phone"
            }
          ]
        }
      ]
    }
  ]
}
```

## Usage

### Run All Seeders

```bash
php artisan db:seed --class=Database\\Seeders\\BeheiraData\\BeheiraDataSeeder
```

### Run Individual Seeders

```bash
# Create governorate
php artisan db:seed --class=Database\\Seeders\\BeheiraData\\GovernorateSeeder

# Create facility types
php artisan db:seed --class=Database\\Seeders\\BeheiraData\\FacilityTypeSeeder

# Create facilities
php artisan db:seed --class=Database\\Seeders\\BeheiraData\\FacilitySeeder

# Create branches
php artisan db:seed --class=Database\\Seeders\\BeheiraData\\FacilityBranchSeeder
```

## Features

- **Automatic Branch Creation**: Every facility gets at least one branch (main branch) using facility's address and phone
- **Multiple Phones**: If a facility has multiple phones, additional branches are created for extra phones
- **Branches Array**: Facilities with explicit `branches` array will have all branches created
- **Location Objects**: Supports `location` as an object with `ar` and `en` keys for multilingual branch locations
- **Hotline Support**: Facilities can have a `hotline` field in addition to or instead of `phones` array
- **Category Mapping**: Categories in JSON are automatically converted to FacilityTypes
- **Translatable Support**: All names and addresses support Arabic and English
- **Nested Array Format**: Data is stored in a big nested associated array structure for easy editing

## Notes

- Facilities without branches array but with address/phone will automatically get a "Main Branch"
- Facilities with multiple phones will have additional branches created for each phone
- Facilities with `hotline` field will use it as the primary phone if `phones` array is empty
- Branch `location` can be a string or an object with `ar` and `en` keys
- Empty `phones` arrays are handled gracefully
- All facilities are ensured to have at least 1 branch with address and phone


