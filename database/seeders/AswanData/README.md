# Aswan Data Seeders

This folder contains seeders for importing medical facilities data for Aswan governorate from JSON format.

## Structure

- `GovernorateSeeder.php` - Creates Aswan governorate
- `FacilityTypeSeeder.php` - Creates facility types from categories in JSON
- `FacilitySeeder.php` - Creates facilities from JSON data
- `FacilityBranchSeeder.php` - Creates branches for facilities (handles branches array and multiple phones)
- `AswanDataSeeder.php` - Main seeder that runs all seeders in correct order
- `aswan.json` - JSON data file containing all facility information

## JSON Structure

The JSON file should follow this structure:

```json
{
  "governorate": {
    "ar": "أسوان",
    "en": "Aswan"
  },
  "medical_directory": [
    {
      "category": { "ar": "المستشفيات", "en": "Hospitals" },
      "facilities": [
        {
          "name": { "ar": "Facility Name AR", "en": "Facility Name EN" },
          "address": { "ar": "Address AR", "en": "Address EN" },
          "phones": ["phone1", "phone2"],
          "branches": [
            {
              "location": { "ar": "Location AR", "en": "Location EN" },
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
php artisan db:seed --class=Database\\Seeders\\AswanData\\AswanDataSeeder
```

### Run Individual Seeders

```bash
# Create governorate
php artisan db:seed --class=Database\\Seeders\\AswanData\\GovernorateSeeder

# Create facility types
php artisan db:seed --class=Database\\Seeders\\AswanData\\FacilityTypeSeeder

# Create facilities
php artisan db:seed --class=Database\\Seeders\\AswanData\\FacilitySeeder

# Create branches
php artisan db:seed --class=Database\\Seeders\\AswanData\\FacilityBranchSeeder
```

## Features

- **Automatic Branch Creation**: Every facility gets at least one branch (main branch) using facility's address and phone
- **Multiple Phones**: If a facility has multiple phones, additional branches are created for extra phones
- **Branches Array**: Facilities with explicit `branches` array will have all branches created
- **Category Mapping**: Categories in JSON are automatically converted to FacilityTypes
- **Translatable Support**: All names and addresses support Arabic and English
- **Location Object Support**: Branch locations can be either strings or objects with `ar` and `en` keys

## Notes

- Facilities without branches array but with address/phone will automatically get a "Main Branch"
- Facilities with multiple phones will have additional branches created for each phone
- Facility "معمل د. مؤمنة كامل (المختبر)" with 2 branches will have all 2 branches created
- All facilities are ensured to have at least 1 branch with address and phone
- Branch locations in the JSON can be either strings or objects with Arabic and English translations


