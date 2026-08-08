#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script to restructure all JSON files to move address and phones from facilities to branches
Every facility must have at least one branch
"""

import json
import os
import glob
from pathlib import Path

def restructure_facility(facility):
    """Restructure a single facility to ensure it has branches"""
    # If facility already has branches, ensure they're properly structured
    if 'branches' in facility and isinstance(facility['branches'], list):
        # Restructure existing branches
        restructured_branches = []
        for branch in facility['branches']:
            restructured_branch = {}
            
            # Handle location
            if 'location' in branch:
                restructured_branch['location'] = branch['location']
            elif 'address' in branch:
                restructured_branch['location'] = branch['address']
            
            # Handle phones
            if 'phones' in branch and isinstance(branch['phones'], list):
                restructured_branch['phones'] = branch['phones']
            elif 'phone' in branch:
                restructured_branch['phone'] = branch['phone']
            
            if restructured_branch:
                restructured_branches.append(restructured_branch)
        
        # Remove address and phones from facility level
        facility.pop('address', None)
        facility.pop('phones', None)
        facility.pop('phone', None)
        facility.pop('hotline', None)
        
        if restructured_branches:
            facility['branches'] = restructured_branches
        
        return facility
    
    # If facility has address or phones at facility level, convert to branch
    if 'address' in facility or 'phones' in facility or 'phone' in facility or 'hotline' in facility:
        branch = {}
        
        if 'address' in facility:
            branch['location'] = facility['address']
        
        if 'phones' in facility and isinstance(facility['phones'], list):
            branch['phones'] = facility['phones']
        elif 'phone' in facility:
            branch['phone'] = facility['phone']
        elif 'hotline' in facility:
            branch['phone'] = facility['hotline']
        
        # Remove address and phones from facility level
        facility.pop('address', None)
        facility.pop('phones', None)
        facility.pop('phone', None)
        facility.pop('hotline', None)
        
        # Add branch
        if branch:
            facility['branches'] = [branch]
    
    # Ensure facility has at least one branch
    if 'branches' not in facility or not facility['branches']:
        # Create a default branch with empty location if no data available
        facility['branches'] = [
            {
                "location": {
                    "ar": "",
                    "en": ""
                }
            }
        ]
    
    return facility

def restructure_json_file(file_path):
    """Restructure a single JSON file"""
    if not os.path.exists(file_path):
        print(f"File not found: {file_path}")
        return False
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        if not data or 'medical_directory' not in data:
            print(f"Invalid JSON structure in: {file_path}")
            return False
        
        modified = False
        
        # Process each category
        for category in data['medical_directory']:
            if 'facilities' not in category or not isinstance(category['facilities'], list):
                continue
            
            # Process each facility
            for facility in category['facilities']:
                original = json.dumps(facility, ensure_ascii=False, sort_keys=True)
                restructure_facility(facility)
                new = json.dumps(facility, ensure_ascii=False, sort_keys=True)
                
                if original != new:
                    modified = True
        
        if modified:
            with open(file_path, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            print(f"✓ Restructured: {file_path}")
            return True
        else:
            print(f"- No changes needed: {file_path}")
            return False
            
    except Exception as e:
        print(f"Error processing {file_path}: {e}")
        return False

def main():
    # Get all JSON files in seeders directory
    seeders_dir = Path(__file__).parent
    json_files = list(seeders_dir.glob('*/*.json'))
    
    if not json_files:
        print("No JSON files found in seeders directory")
        return
    
    print(f"Found {len(json_files)} JSON files")
    print("Restructuring files...\n")
    
    restructured = 0
    for file_path in sorted(json_files):
        if restructure_json_file(file_path):
            restructured += 1
    
    print(f"\n=== Summary ===")
    print(f"Total files: {len(json_files)}")
    print(f"Restructured: {restructured}")
    print(f"No changes needed: {len(json_files) - restructured}")

if __name__ == '__main__':
    main()


