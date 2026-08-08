# South Sinai Data Seeders

This folder contains seeders for importing medical facilities data for South Sinai governorate from JSON format.

## Structure

- `GovernorateSeeder.php` - Creates South Sinai governorate
- `FacilityTypeSeeder.php` - Creates facility types from categories in JSON
- `FacilitySeeder.php` - Creates facilities from JSON data
- `FacilityBranchSeeder.php` - Creates branches for facilities (handles branches array, location objects, string arrays, phones arrays, and hotline)
- `SouthSinaiDataSeeder.php` - Main seeder that runs all seeders in correct order
- `south-sinai.json` - JSON data file containing all facility information in nested associated array format

## JSON Structure

The JSON file follows this structure with nested associated arrays for easy editing:

```json
{
  "governorate": {
    "ar": "جنوب سيناء",
    "en": "South Sinai"
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
            // Can be array of strings: ["Location1", "Location2"]
            // Or array of objects:
            {
              "location": { "ar": "Branch location AR", "en": "Branch location EN" },
              "location": "Branch location string",
              "phone": "branch phone",
              "phones": ["phone1", "phone2"] // Branch can have multiple phones
            }
          ],
          "specialty": { "ar": "Specialty AR", "en": "Specialty EN" } // Optional, for doctors
        }
      ]
    }
  ]
}
```

## Usage

### Run All Seeders

```bash
php artisan db:seed --class=Database\\Seeders\\SouthSinaiData\\SouthSinaiDataSeeder
```

### Run Individual Seeders

```bash
# Create governorate
php artisan db:seed --class=Database\\Seeders\\SouthSinaiData\\GovernorateSeeder

# Create facility types
php artisan db:seed --class=Database\\Seeders\\SouthSinaiData\\FacilityTypeSeeder

# Create facilities
php artisan db:seed --class=Database\\Seeders\\SouthSinaiData\\FacilitySeeder

# Create branches
php artisan db:seed --class=Database\\Seeders\\SouthSinaiData\\FacilityBranchSeeder
```

## Features

- **Automatic Branch Creation**: Every facility gets at least one branch (main branch) using facility's address and phone
- **Multiple Phones**: If a facility has multiple phones, additional branches are created for extra phones
- **Branches Array**: Supports both string arrays and object arrays
- **Branch Phones Array**: Branches can have a `phones` array (creates multiple branches for same location with different phones)
- **Location Objects**: Supports `location` as an object with `ar` and `en` keys for multilingual branch locations
- **Location Strings**: Supports `location` as a simple string in branch objects
- **Branches Without Phones**: Handles branches that only have location (no phone field)
- **Hotline Support**: Facilities can have a `hotline` field in addition to or instead of `phones` array
- **Category Mapping**: Categories in JSON are automatically converted to FacilityTypes
- **Translatable Support**: All names and addresses support Arabic and English
- **Nested Array Format**: Data is stored in a big nested associated array structure for easy editing
- **String Branches with Multiple Phones**: When branches are simple strings and facility has multiple phones, phones are distributed across branches

## Notes

- Facilities without branches array but with address/phone will automatically get a "Main Branch"
- Facilities with multiple phones will have additional branches created for each phone
- Facilities with `hotline` field will use it as the primary phone if `phones` array is empty
- Branch `location` can be a string or an object with `ar` and `en` keys
- Branches can have `phone` (single) or `phones` (array) - if `phones` array, creates multiple branches for same location
- Branches can have location without phone (phone will be taken from facility's hotline/phones if available)
- `branches` can be an array of strings (simple location names) or an array of objects
- When branches are strings and facility has multiple phones, phones are distributed across branches using modulo
- Empty `phones` arrays are handled gracefully
- All facilities are ensured to have at least 1 branch with address and phone
- The `specialty` field in the JSON is preserved but not currently stored in the database (can be added to schema if needed)


