# Ismailia Data Seeders

This folder contains seeders for importing medical facilities data for Ismailia governorate from JSON format.

## Structure

- `GovernorateSeeder.php` - Creates Ismailia governorate
- `FacilityTypeSeeder.php` - Creates facility types from categories in JSON
- `FacilitySeeder.php` - Creates facilities from JSON data
- `FacilityBranchSeeder.php` - Creates branches for facilities (handles branches array and multiple phones)
- `IsmailiaDataSeeder.php` - Main seeder that runs all seeders in correct order
- `ismailia.json` - JSON data file containing all facility information

## JSON Structure

The JSON file should follow this structure:

```json
{
  "governorate": {
    "ar": "الإسماعيلية",
    "en": "Ismailia"
  },
  "medical_directory": [
    {
      "category": { "ar": "المستشفيات", "en": "Hospitals" },
      "facilities": [
        {
          "name": { "ar": "Facility Name AR", "en": "Facility Name EN" },
          "address": { "ar": "Address AR", "en": "Address EN" },
          "phones": ["phone1", "phone2"],
          "hotline": "hotline number",
          "branches": [
            {
              "address": { "ar": "Address AR", "en": "Address EN" },
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
php artisan db:seed --class=Database\\Seeders\\IsmailiaData\\IsmailiaDataSeeder
```

### Run Individual Seeders

```bash
# Create governorate
php artisan db:seed --class=Database\\Seeders\\IsmailiaData\\GovernorateSeeder

# Create facility types
php artisan db:seed --class=Database\\Seeders\\IsmailiaData\\FacilityTypeSeeder

# Create facilities
php artisan db:seed --class=Database\\Seeders\\IsmailiaData\\FacilitySeeder

# Create branches
php artisan db:seed --class=Database\\Seeders\\IsmailiaData\\FacilityBranchSeeder
```

## Features

- **Automatic Branch Creation**: Every facility gets at least one branch (main branch) using facility's address and phone
- **Multiple Phones**: If a facility has multiple phones, additional branches are created for extra phones
- **Branches Array**: Facilities with explicit `branches` array will have all branches created
- **Category Mapping**: Categories in JSON are automatically converted to FacilityTypes
- **Translatable Support**: All names and addresses support Arabic and English
- **Hotline Support**: Facilities can use `hotline` field instead of `phones` array
- **Address Object Support**: Branch addresses can be either strings or objects with `ar` and `en` keys
- **Empty Phones Handling**: Facilities with empty phones arrays are still created with branches
- **Fixed JSON Encoding**: Proper string casting ensures MySQL JSON fields work correctly

## Notes

- Facilities without branches array but with address/phone will automatically get a "Main Branch"
- Facilities with multiple phones will have additional branches created for each phone
- Facilities with `hotline` field will use it as the primary phone number
- Branch addresses in the JSON can be either strings or objects with Arabic and English translations
- Facilities with empty `phones: []` arrays will still be created if they have an address
- All facilities are ensured to have at least 1 branch with address and phone
- All string values are properly cast to ensure MySQL JSON encoding works correctly


