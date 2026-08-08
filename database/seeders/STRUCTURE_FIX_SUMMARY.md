# JSON Structure Fix Summary

## ✅ Files Already Fixed

1. **Giza (giza.json)** - ✅ COMPLETE
   - All 29 facilities now use branches structure
   - All address/phones moved to branches
   - All string branches converted to objects

2. **Qalyubia (qalyubia.json)** - ✅ COMPLETE
   - All 22 facilities now use branches structure
   - All address/phones moved to branches
   - Tabarak Children's Hospital branches fixed

3. **Cairo (cairo.json)** - ✅ VERIFIED OK
   - Already uses proper branches structure
   - No changes needed

## ⚠️ Files That Need Fixing

The following files likely need structure fixes (address/phones at facility level or branches with wrong format):

- AlexandriaData/alexandria.json
- AssiutData/assiut.json
- AswanData/aswan.json
- BeheiraData/beheira.json
- BeniSuefData/beni-suef.json
- DakahliaData/dakahlia.json
- DamiettaData/damietta.json
- FayoumData/fayoum.json
- GharbiaData/gharbia.json
- IsmailiaData/ismailia.json
- KafrElSheikhData/kafr-el-sheikh.json
- LuxorData/luxor.json
- MarsaMatrouhData/marsa-matrouh.json (has address at facility level)
- MenofiaData/menofia.json (has address/phones at facility level, phone instead of phones in branches)
- MinyaData/minya.json
- NewValleyData/new-valley.json
- NorthSinaiData/north-sinai.json
- PortSaidData/port-said.json
- QenaData/qena.json
- RedSeaData/red-sea.json
- SharqiaData/sharqia.json
- SohagData/sohag.json
- SouthSinaiData/south-sinai.json
- SuezData/suez.json
- SuezGharbiaData/suez-gharbia.json

## 🔧 How to Fix All Files

### Automated Fix (Recommended)

Run the Python script to automatically fix all files:

```bash
cd database/seeders
python3 fix_json_structure.py
```

This script will:
- ✅ Find all JSON files
- ✅ Check each facility for structure issues
- ✅ Automatically fix:
  - Move address/phones from facility level to branches
  - Convert string branches to proper objects
  - Fix `address` field in branches to `location`
  - Fix `phone` (singular) to `phones` (array)
  - Remove `hotline` from facility level (move to branch)
- ✅ Report which files were modified
- ✅ Preserve all existing data

### Manual Validation After Running Script

After running the script, verify:
1. No facilities have `address` or `phones` at facility level
2. Every facility has at least one `branch`
3. All branches use `location` (not `address`)
4. All branches use `phones` array (not `phone`)

## 📋 Structure Requirements

### ✅ Correct Structure:
```json
{
  "name": {
    "ar": "اسم المنشأة",
    "en": "Facility Name"
  },
  "branches": [
    {
      "location": {
        "ar": "العنوان بالعربي",
        "en": "Address in English"
      },
      "phones": ["123456", "789012"]
    }
  ]
}
```

### ❌ Incorrect Structures to Fix:
```json
// Wrong: address/phones at facility level
{
  "name": {...},
  "address": {...},  // ❌ Should be in branch
  "phones": [...]    // ❌ Should be in branch
}

// Wrong: string branches
{
  "branches": ["Location 1", "Location 2"]  // ❌ Should be objects
}

// Wrong: address instead of location in branch
{
  "branches": [{
    "address": {...},  // ❌ Should be "location"
    "phone": "123"     // ❌ Should be "phones": ["123"]
  }]
}
```

## Next Steps

1. **Run the automated script**: `python3 fix_json_structure.py`
2. **Review the output** to see which files were modified
3. **Validate JSON files** are properly formatted (no syntax errors)
4. **Test seeders** to ensure they work with the new structure

The script is safe to run multiple times - it will only fix files that need fixing.

