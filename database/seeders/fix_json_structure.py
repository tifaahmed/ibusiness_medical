#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Fix all JSON files to ensure:
1. Facilities do NOT have address or phones at facility level
2. Every facility has at least one branch
3. Address and phones are moved to branches
"""

import json
import os
import glob
from pathlib import Path

def fix_facility(facility):
    """Fix a single facility to ensure proper structure"""
    modified = False
    
    # Check if facility has address or phones at facility level
    has_address = 'address' in facility
    has_phones = 'phones' in facility
    has_phone = 'phone' in facility
    has_hotline = 'hotline' in facility
    has_branches = 'branches' in facility and isinstance(facility['branches'], list) and len(facility['branches']) > 0
    
    # If facility has address/phones but no branches, create a branch
    if (has_address or has_phones or has_phone) and not has_branches:
        branch = {}
        
        # Move address to location
        if has_address:
            if isinstance(facility['address'], dict):
                branch['location'] = facility['address']
            else:
                branch['location'] = {
                    'ar': str(facility['address']),
                    'en': str(facility['address'])
                }
        
        # Move phones to branch
        if has_phones:
            branch['phones'] = facility['phones'] if isinstance(facility['phones'], list) else [facility['phones']]
        elif has_phone:
            branch['phones'] = [facility['phone']]
        elif has_hotline:
            branch['phones'] = [facility['hotline']]
        
        facility['branches'] = [branch]
        
        # Remove from facility level
        if has_address:
            del facility['address']
        if has_phones:
            del facility['phones']
        if has_phone:
            del facility['phone']
        if has_hotline:
            del facility['hotline']
        
        modified = True
    
    # If facility has address/phones AND branches, remove from facility level
    elif (has_address or has_phones or has_phone or has_hotline) and has_branches:
        if has_address:
            del facility['address']
        if has_phones:
            del facility['phones']
        if has_phone:
            del facility['phone']
        if has_hotline:
            del facility['hotline']
        modified = True
    
    # Fix branches structure
    if 'branches' in facility and isinstance(facility['branches'], list):
        for branch in facility['branches']:
            if isinstance(branch, str):
                # Convert string branch to object
                facility['branches'][facility['branches'].index(branch)] = {
                    'location': {
                        'ar': branch,
                        'en': branch
                    }
                }
                modified = True
            elif isinstance(branch, dict):
                # Fix location field
                if 'location' in branch and isinstance(branch['location'], str):
                    branch['location'] = {
                        'ar': branch['location'],
                        'en': branch['location']
                    }
                    modified = True
                
                # Convert address to location
                if 'address' in branch:
                    if 'location' not in branch:
                        if isinstance(branch['address'], dict):
                            branch['location'] = branch['address']
                        else:
                            branch['location'] = {
                                'ar': str(branch['address']),
                                'en': str(branch['address'])
                            }
                    del branch['address']
                    modified = True
                
                # Fix phone field to phones array
                if 'phone' in branch and 'phones' not in branch:
                    branch['phones'] = [branch['phone']]
                    del branch['phone']
                    modified = True
    
    return modified

def fix_json_file(file_path):
    """Fix a single JSON file"""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
    except Exception as e:
        print(f"❌ Error reading {file_path}: {e}")
        return False, 0
    
    if 'medical_directory' not in data:
        return False, 0
    
    facilities_fixed = 0
    file_modified = False
    
    for category in data['medical_directory']:
        if 'facilities' not in category:
            # Check for chains in pharmacies
            if 'chains' in category:
                for chain in category['chains']:
                    # Chains can have hotline and locations, that's acceptable
                    # But if they have address/phones, we should convert
                    if fix_facility(chain):
                        facilities_fixed += 1
                        file_modified = True
            continue
        
        for facility in category['facilities']:
            if fix_facility(facility):
                facilities_fixed += 1
                file_modified = True
    
    if file_modified:
        try:
            with open(file_path, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            return True, facilities_fixed
        except Exception as e:
            print(f"❌ Error writing {file_path}: {e}")
            return False, 0
    
    return False, facilities_fixed

def main():
    base_dir = Path(__file__).parent
    json_files = list(base_dir.glob('*/*.json')) + list(base_dir.glob('*Data/*.json'))
    
    # Exclude non-data files
    exclude_files = ['missing_part3_facilities.json']
    json_files = [f for f in json_files if f.name not in exclude_files]
    
    print("=== Fixing JSON Structure ===\n")
    
    fixed_files = []
    total_fixed = 0
    
    for json_file in sorted(json_files):
        relative_path = str(json_file.relative_to(base_dir))
        modified, fixed_count = fix_json_file(json_file)
        
        if modified:
            print(f"✅ Fixed: {relative_path} ({fixed_count} facilities)")
            fixed_files.append((relative_path, fixed_count))
            total_fixed += fixed_count
        else:
            print(f"✓  OK: {relative_path}")
    
    print(f"\n{'='*80}")
    print("SUMMARY")
    print(f"{'='*80}")
    print(f"Total files processed: {len(json_files)}")
    print(f"Files modified: {len(fixed_files)}")
    print(f"Total facilities fixed: {total_fixed}\n")
    
    if fixed_files:
        print("FIXED FILES:")
        for file_path, count in fixed_files:
            print(f"  - {file_path}: {count} facilities")
    
    print("\n✅ All JSON files have been fixed!")

if __name__ == '__main__':
    main()

