# JSON Structure Fix Report

## Summary

This document reports the fixes applied to ensure all JSON seeder files follow the correct structure:
1. Facilities should NOT have `address` or `phones` at the facility level
2. Every facility must have at least one `branch`
3. Address and phones must be inside branches, using `location` (not `address`) and `phones` array

## Files Fixed

### ✅ Giza (giza.json)
**Status:** COMPLETED
- Fixed 14 facilities in "General Hospitals" category
- Fixed 4 facilities in "Eye Centers and Hospitals" category  
- Fixed 3 facilities in "Heart and Catheterization Centers" category
- Fixed 4 facilities in "Laboratories and Radiology Centers" category (including string branches)
- Fixed 3 facilities in "Physical Therapy and Rehab" category
- Fixed 1 facility in "Optics" category
- **Total:** 29 facilities fixed

**Key Changes:**
- Converted all `address` and `phones` at facility level to `branches` with `location` and `phones`
- Fixed branches that were strings to proper objects with location structure
- Fixed branches with `address` field to use `location` instead
- Fixed branches with `phone` (singular) to `phones` (array)

### ✅ Qalyubia (qalyubia.json)
**Status:** COMPLETED
- Fixed 8 facilities in "Hospitals" category (including Tabarak Children's Hospital branches)
- Fixed 2 facilities in "Physical Therapy and Rehabilitation" category
- Fixed 2 facilities in "Radiology Centers" category
- Fixed 3 facilities in "Medical Laboratories" category
- Fixed 3 facilities in "Doctors and Clinics" category
- Fixed 2 facilities in "Pharmacies" category
- Fixed 2 facilities in "Optics" category
- **Total:** 22 facilities fixed

**Key Changes:**
- Converted all `address` and `phones` at facility level to `branches`
- Fixed Tabarak Children's Hospital branches to use proper location structure
- Fixed all branches with string locations to use location objects

## Files That May Need Fixing

The following files need to be checked and fixed if they have similar issues:

1. AlexandriaData/alexandria.json
2. AssiutData/assiut.json
3. AswanData/aswan.json
4. BeheiraData/beheira.json
5. BeniSuefData/beni-suef.json
6. CairoData/cairo.json (may be OK - uses branches)
7. CairoGizaData/cairo-giza.json
8. DakahliaData/dakahlia.json
9. DamiettaData/damietta.json
10. FayoumData/fayoum.json
11. GharbiaData/gharbia.json
12. IsmailiaData/ismailia.json
13. KafrElSheikhData/kafr-el-sheikh.json
14. LuxorData/luxor.json
15. MarsaMatrouhData/marsa-matrouh.json
16. MenofiaData/menofia.json
17. MinyaData/minya.json
18. NewValleyData/new-valley.json
19. NorthSinaiData/north-sinai.json
20. PortSaidData/port-said.json
21. QenaData/qena.json
22. RedSeaData/red-sea.json
23. SharqiaData/sharqia.json
24. SohagData/sohag.json
25. SouthSinaiData/south-sinai.json
26. SuezData/suez.json
27. SuezGharbiaData/suez-gharbia.json

## How to Fix Remaining Files

### Option 1: Use the Python Script (Recommended)

Run the provided Python script to automatically fix all files:

```bash
cd database/seeders
python3 fix_json_structure.py
```

The script will:
- Find all JSON files in the seeders directory
- Check each facility for structure issues
- Automatically fix:
  - Move address/phones from facility level to branches
  - Convert string branches to proper objects
  - Fix `address` field in branches to `location`
  - Fix `phone` (singular) to `phones` (array)
- Report which files were modified

### Option 2: Manual Fixing

For each file, ensure:

1. **Facilities with address/phones at facility level:**
   ```json
   // ❌ WRONG
   {
     "name": { "ar": "...", "en": "..." },
     "address": { "ar": "...", "en": "..." },
     "phones": ["123", "456"]
   }
   
   // ✅ CORRECT
   {
     "name": { "ar": "...", "en": "..." },
     "branches": [
       {
         "location": { "ar": "...", "en": "..." },
         "phones": ["123", "456"]
       }
     ]
   }
   ```

2. **Branches must be objects, not strings:**
   ```json
   // ❌ WRONG
   "branches": ["Location 1", "Location 2"]
   
   // ✅ CORRECT
   "branches": [
     {
       "location": { "ar": "الموقع 1", "en": "Location 1" },
       "phones": ["123"]
     }
   ]
   ```

3. **Use `location` not `address` in branches:**
   ```json
   // ❌ WRONG
   {
     "address": { "ar": "...", "en": "..." },
     "phone": "123"
   }
   
   // ✅ CORRECT
   {
     "location": { "ar": "...", "en": "..." },
     "phones": ["123"]
   }
   ```

## Validation Checklist

After fixing, each facility should have:
- ✅ `name` object with `ar` and `en`
- ✅ `branches` array (at least one branch)
- ✅ Each branch has:
  - ✅ `location` object with `ar` and `en` (NOT `address`)
  - ✅ `phones` array (NOT `phone` or `hotline`)
- ✅ NO `address`, `phones`, `phone`, or `hotline` at facility level

## Special Cases

### Pharmacy Chains
Some pharmacy chains may use `chains` instead of `facilities` and may have `hotline` and `locations` array. This is acceptable for chains, but individual facilities should still use branches structure.

### Facilities with Multiple Branches
Facilities can have multiple branches, each with its own location and phones:
```json
{
  "name": { "ar": "...", "en": "..." },
  "branches": [
    {
      "location": { "ar": "الفرع 1", "en": "Branch 1" },
      "phones": ["111"]
    },
    {
      "location": { "ar": "الفرع 2", "en": "Branch 2" },
      "phones": ["222"]
    }
  ]
}
```

## Next Steps

1. Run the Python script to fix all remaining files automatically
2. Validate all JSON files are properly formatted
3. Test the seeders to ensure they work correctly with the new structure
4. Update any seeder PHP files if they expect the old structure

