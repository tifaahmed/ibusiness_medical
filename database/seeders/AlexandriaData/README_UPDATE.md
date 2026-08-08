# Updating Alexandria JSON Data

## Current Status

The current `alexandria.json` file is **INCOMPLETE**. It only contains a small subset of the facilities from the spreadsheet.

## To Update the JSON File

Run the update script:

```bash
cd database/seeders/AlexandriaData
php update_from_html.php
```

This script will:
1. Parse the HTML file from `C:\Users\nasse\Downloads\الاسكندرية.html`
2. Extract all facilities using the AlexandriaDataParser
3. Group them by category
4. Update `alexandria.json` with all facilities

## Manual Alternative

If the parser doesn't work correctly, you can manually update the JSON file by:
1. Opening the HTML file in a browser or text editor
2. Extracting facility data row by row
3. Adding them to the appropriate categories in `alexandria.json`

## Missing Data Summary

The current JSON is missing:
- ~23+ hospitals
- Multiple specialized hospitals
- Many labs and radiology centers
- Many pharmacies
- All doctors entries
- Physical therapy centers
- Prosthetic devices companies
- Many optics centers


