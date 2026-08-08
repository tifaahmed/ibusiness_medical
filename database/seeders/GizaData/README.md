# Giza Data Seeders

This folder contains seeders for importing medical facilities data for Giza governorate from JSON format.

## Structure

- `GovernorateSeeder.php` - Creates Giza governorate
- `FacilityTypeSeeder.php` - Creates facility types from categories in JSON
- `FacilitySeeder.php` - Creates facilities from JSON data (handles both `facilities` and `chains` arrays)
- `FacilityBranchSeeder.php` - Creates branches for facilities (handles branches array, location objects, string arrays, and locations array)
- `GizaDataSeeder.php` - Main seeder that runs all seeders in correct order
- `giza.json` - JSON data file containing all facility information in nested associated array format

## JSON Structure

The JSON file follows this structure with nested associated arrays for easy editing. Giza has some unique formats:

```json
{
  "governorate": {
    "ar": "الجيزة",
    "en": "Giza"
  },
  "medical_directory": [
    {
      "category": { "ar": "المستشفيات العامة", "en": "General Hospitals" },
      "facilities": [
        {
          "name": { "ar": "Facility Name AR", "en": "Facility Name EN" },
          "address": { "ar": "Address AR", "en": "Address EN" },
          "phones": ["phone1", "phone2"],
          "hotline": "hotline_number",
          "branches": [
            // Can be array of strings: ["Dokki", "6th of October"]
            // Or array of objects:
            {
              "location": { "ar": "Branch location AR", "en": "Branch location EN" },
              "address": "Branch address string",
              "phone": "branch phone"
            }
          ],
          "locations": ["Location1", "Location2"] // Alternative format
        }
      ],
      "chains": [ // Giza specific - for pharmacy chains
        {
          "name": { "ar": "Chain Name AR", "en": "Chain Name EN" },
          "hotline": "hotline_number",
          "locations": ["Location1", "Location2"]
        }
      ]
    }
  ]
}
```

## Usage

### Run All Seeders

```bash
php artisan db:seed --class=Database\\Seeders\\GizaData\\GizaDataSeeder
```

### Run Individual Seeders

```bash
# Create governorate
php artisan db:seed --class=Database\\Seeders\\GizaData\\GovernorateSeeder

# Create facility types
php artisan db:seed --class=Database\\Seeders\\GizaData\\FacilityTypeSeeder

# Create facilities
php artisan db:seed --class=Database\\Seeders\\GizaData\\FacilitySeeder

# Create branches
php artisan db:seed --class=Database\\Seeders\\GizaData\\FacilityBranchSeeder
```

## Features

- **Automatic Branch Creation**: Every facility gets at least one branch (main branch) using facility's address and phone
- **Multiple Phones**: If a facility has multiple phones, additional branches are created for extra phones
- **Branches Array**: Supports both string arrays (like `["Dokki", "6th of October"]`) and object arrays
- **Locations Array**: Handles `locations` array format (Giza specific for chains and some facilities)
- **Chains Support**: Handles `chains` array for pharmacy chains (treated as facilities)
- **Location Objects**: Supports `location` as an object with `ar` and `en` keys for multilingual branch locations
- **String Addresses**: Handles `address` as a simple string in branch objects
- **Hotline Support**: Facilities can have a `hotline` field in addition to or instead of `phones` array
- **Category Mapping**: Categories in JSON are automatically converted to FacilityTypes
- **Translatable Support**: All names and addresses support Arabic and English
- **Nested Array Format**: Data is stored in a big nested associated array structure for easy editing

## Notes

- Facilities without branches/locations arrays but with address/phone will automatically get a "Main Branch"
- Facilities with multiple phones will have additional branches created for each phone
- Facilities with `hotline` field will use it as the primary phone if `phones` array is empty
- Branch `location` can be a string or an object with `ar` and `en` keys
- Branch `address` can be a string (Giza format) or an object with `ar` and `en` keys
- `branches` can be an array of strings (simple location names) or an array of objects
- `locations` array is treated as simple location names (creates branches with location as address)
- `chains` array is processed the same as `facilities` array
- Empty `phones` arrays are handled gracefully
- All facilities are ensured to have at least 1 branch with address and phone


