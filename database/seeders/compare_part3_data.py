#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import json
import os

# Provided data from user - hospitals only for Part 3
provided_data = {
    'Cairo': {
        'Hospitals': [
            {'name_ar': 'مستشفى دار الحكمة', 'name_en': 'Dar El Hekma Hospital'},
            {'name_ar': 'مستشفى السلام الدولى', 'name_en': 'Al Salam International Hospital'},
            {'name_ar': 'مستشفى النيل بدراوى', 'name_en': 'Nile Badrawy Hospital'},
            {'name_ar': 'مستشفى قصر العينى التعليمى', 'name_en': 'Kasr El Aini Teaching Hospital'},
            {'name_ar': 'مستشفى الجنزورى', 'name_en': 'El Ganzouri Hospital'},
            {'name_ar': 'مستشفى معهد ناصر', 'name_en': 'Nasser Institute Hospital'},
            {'name_ar': 'المجمع الطبى العسكرى بكوبرى القبه', 'name_en': 'Military Medical Complex - Kobri El Qoba'},
            {'name_ar': 'مستشفى القوات المسلحه بالمعادى', 'name_en': 'Armed Forces Hospital - Maadi'},
            {'name_ar': 'المستشفى الايطالى', 'name_en': 'Italian Hospital'},
            {'name_ar': 'مستشفى سان بيتر الدولى', 'name_en': 'San Peter International Hospital'},
            {'name_ar': 'مستشفى المقاولون العرب (19660)', 'name_en': 'Arab Contractors Hospital (19660)'},
            {'name_ar': 'مستشفى غمره العسكرى', 'name_en': 'Ghamra Military Hospital'},
            {'name_ar': 'مستشفى فلسطين', 'name_en': 'Palestine Hospital'},
            {'name_ar': 'مستشفى الهلال برمسيس', 'name_en': 'Crescent Hospital - Ramses'},
            {'name_ar': 'مستشفى الزيتون التخصصى', 'name_en': 'Zeitoun Specialized Hospital'},
            {'name_ar': 'مستشفى شجرة الدر', 'name_en': 'Shagaret El Dorr Hospital'},
            {'name_ar': 'مستشفى الصفوة بشبرا', 'name_en': 'El Safwa Hospital - Shubra'},
        ]
    },
    'Giza': {
        'Hospitals': [
            {'name_ar': 'مستشفى الهرم', 'name_en': 'Haram Hospital'},
            {'name_ar': 'مستشفى الجيزة الدولى', 'name_en': 'Giza International Hospital'},
            {'name_ar': 'مستشفى الامل بالمهندسين', 'name_en': 'Al Amal Hospital - Mohandessin'},
            {'name_ar': 'مستشفى الشبراويشى', 'name_en': 'El Shabraweishy Hospital'},
            {'name_ar': 'مستشفى مصر الدولى', 'name_en': 'Misr International Hospital'},
            {'name_ar': 'مستشفى بدراوى', 'name_en': 'Badrawy Hospital'},
            {'name_ar': 'مستشفى الجزيرة', 'name_en': 'El Gezira Hospital'},
            {'name_ar': 'مستشفى الشيخ زايد التخصصى', 'name_en': 'Sheikh Zayed Specialized Hospital'},
            {'name_ar': 'مستشفى الصفوة (16361)', 'name_en': 'El Safwa Hospital (16361)'},
            {'name_ar': 'مستشفى الوادى', 'name_en': 'El Wadi Hospital'},
            {'name_ar': 'مستشفي دريم', 'name_en': 'Dream Hospital'},
            {'name_ar': 'مستشفي جلوبال كير', 'name_en': 'Global Care Hospital'},
        ]
    },
    'Qalyubia': {
        'Hospitals': [
            {'name_ar': 'مستشفى الامل بشبرا الخيمة', 'name_en': 'Al Amal Hospital - Shubra El Kheima'},
            {'name_ar': 'مستشفى الفيومى', 'name_en': 'El Fayoumy Hospital'},
            {'name_ar': 'مستشفى الراعى الصالح', 'name_en': 'Good Shepherd Hospital'},
            {'name_ar': 'مستشفى صلاح الدين', 'name_en': 'Salah El Din Hospital'},
            {'name_ar': 'مستشفى الصفا', 'name_en': 'El Safa Hospital'},
            {'name_ar': 'مستشفى تبارك للأطفال', 'name_en': 'Tabarak Children Hospital'},
        ]
    },
    'Menoufia': {
        'Hospitals': [
            {'name_ar': 'مستشفى المعلمين الجديد', 'name_en': 'New Teachers Hospital'},
            {'name_ar': 'مستشفى المواساة الاسلامى', 'name_en': 'Al Mawasah Islamic Hospital'},
            {'name_ar': 'مستشفي السادات التخصصي', 'name_en': 'Sadat Specialized Hospital'},
            {'name_ar': 'مستشفى الرواد التخصصى', 'name_en': 'Pioneers Specialized Hospital'},
            {'name_ar': 'مستشفى عرفة التخصصى', 'name_en': 'Arafa Specialized Hospital'},
        ]
    },
    'North Sinai': {
        'Hospitals': [
            {'name_ar': 'مستشفى العريش العسكرى', 'name_en': 'Arish Military Hospital'},
            {'name_ar': 'مستشفى سيناء التخصصى', 'name_en': 'Sinai Specialized Hospital'},
        ],
        'Laboratories': [
            {'name_ar': 'معمل د. سمير شاكر', 'name_en': 'Dr. Samir Shaker Lab'},
            {'name_ar': 'معمل سينا لاب', 'name_en': 'Sina Lab'},
        ],
        'Pharmacies': [
            {'name_ar': 'صيدلية احمد و على', 'name_en': 'Ahmed & Ali Pharmacy'},
            {'name_ar': 'صيدلية دراهم', 'name_en': 'Darahem Pharmacy'},
            {'name_ar': 'صيدلية د.محمد الغالى', 'name_en': 'Dr. Mohamed El Ghaly Pharmacy'},
            {'name_ar': 'صيدية الشوربجى', 'name_en': 'El Shorbagi Pharmacy'},
        ]
    },
    'South Sinai': {
        'Hospitals': [
            {'name_ar': 'مستشفى شرم الشيخ الدولى', 'name_en': 'Sharm El Sheikh International Hospital'},
            {'name_ar': 'مستشفى جبل سيناء', 'name_en': 'Mount Sinai Hospital'},
            {'name_ar': 'مستشفي جنوب سيناء', 'name_en': 'South Sinai Hospital'},
            {'name_ar': 'مستشفى مبارك العسكرى بالطور', 'name_en': 'Mubarak Military Hospital - El Tor'},
        ],
        'Laboratories': [
            {'name_ar': 'معمل الفيروز للتحاليل الطبية', 'name_en': 'Al Fayrouz Medical Lab'},
        ],
        'Pharmacies': [
            {'name_ar': 'صيدليات العزبى', 'name_en': 'El Ezaby Pharmacies'},
            {'name_ar': 'صيدلية الشناوى الجديدة', 'name_en': 'New El Shenawy Pharmacy'},
        ]
    },
    'Marsa Matrouh': {
        'Hospitals': [
            {'name_ar': 'مستشفى مطروح العسكرى', 'name_en': 'Matrouh Military Hospital'},
            {'name_ar': 'مستشفى عبد الله عيسى التخصصى', 'name_en': 'Abdullah Eissa Specialized Hospital'},
        ],
        'Laboratories': [
            {'name_ar': 'معمل د.مؤمنة كامل (المختبر)', 'name_en': 'Dr. Moumena Kamel Lab'},
            {'name_ar': 'معامل الفا', 'name_en': 'Alfa Labs'},
            {'name_ar': 'معمل البرج', 'name_en': 'Al Borg Lab'},
        ],
        'Radiology Centers': [
            {'name_ar': 'تكنو سكان اسامة خليل', 'name_en': 'Techno Scan Osama Khalil'},
        ],
        'Pharmacies': [
            {'name_ar': 'صيدية نوح', 'name_en': 'Nouh Pharmacy'},
            {'name_ar': 'صيدية الحلوانى', 'name_en': 'El Helwany Pharmacy'},
            {'name_ar': 'صيدلية د/ فضل مطير', 'name_en': 'Dr. Fadl Matir Pharmacy'},
        ]
    },
    'New Valley': {
        'Hospitals': [
            {'name_ar': 'مستشفى السلام - د. احمد صالح', 'name_en': 'Al Salam Hospital - Dr. Ahmed Saleh'},
            {'name_ar': 'مستشفى هند التخصصى', 'name_en': 'Hend Specialized Hospital'},
        ],
        'Laboratories': [
            {'name_ar': 'معمل المختبر', 'name_en': 'Al Mokhtar Lab'},
            {'name_ar': 'معمل البرج', 'name_en': 'Al Borg Lab'},
        ],
        'Pharmacies': [
            {'name_ar': 'ص. محمد سعد', 'name_en': 'Mohamed Saad Pharmacy'},
            {'name_ar': 'صيدليه ايمان محمد حسين', 'name_en': 'Iman Mohamed Hussein Pharmacy'},
        ]
    },
    'Red Sea': {
        'Hospitals': [
            {'name_ar': 'مستشفى السلام الغردقة', 'name_en': 'Al Salam Hospital Hurghada'},
            {'name_ar': 'مستشفى البحر الاحمر', 'name_en': 'Red Sea Hospital'},
            {'name_ar': 'مستشفى الحكمة للخدمات الطبية', 'name_en': 'Al Hekma Medical Services Hospital'},
            {'name_ar': 'المستشفى المصرى بالغردقة', 'name_en': 'Egyptian Hospital Hurghada'},
        ],
        'Laboratories': [
            {'name_ar': 'معامل الفا', 'name_en': 'Alfa Labs'},
            {'name_ar': 'معامل النخبة', 'name_en': 'Elite Labs'},
            {'name_ar': 'معمل د.مؤمنة كامل (المختبر)', 'name_en': 'Dr. Moumena Kamel Lab'},
            {'name_ar': 'معامل البركه للتحاليل الطبيه', 'name_en': 'Al Baraka Medical Labs'},
        ],
        'Pharmacies': [
            {'name_ar': 'صيدليات العزبى', 'name_en': 'El Ezaby Pharmacies'},
            {'name_ar': 'صيدلية عبير', 'name_en': 'Abeer Pharmacy'},
        ]
    }
}

file_map = {
    'Cairo': 'CairoData/cairo.json',
    'Giza': 'GizaData/giza.json',
    'Qalyubia': 'QalyubiaData/qalyubia.json',
    'Menoufia': 'MenofiaData/menofia.json',
    'North Sinai': 'NorthSinaiData/north-sinai.json',
    'South Sinai': 'SouthSinaiData/south-sinai.json',
    'Marsa Matrouh': 'MarsaMatrouhData/marsa-matrouh.json',
    'New Valley': 'NewValleyData/new-valley.json',
    'Red Sea': 'RedSeaData/red-sea.json',
}

category_keywords = {
    'Hospitals': ['المستشفيات', 'مستشفيات', 'Hospitals'],
    'Laboratories': ['معامل التحاليل', 'معامل', 'Laboratories', 'Laboratory'],
    'Pharmacies': ['الصيدليات', 'صيدليات', 'صيدلية', 'Pharmacies', 'Pharmacy'],
    'Radiology Centers': ['مراكز اشعة', 'مراكز الأشعة', 'Radiology', 'Radiation'],
}

def normalize_arabic(text):
    """Normalize Arabic text for comparison"""
    if not text:
        return ''
    # Remove common prefixes
    text = text.replace('مستشفى ', '').replace('مستشفي ', '').replace('مستشفيات ', '')
    text = text.replace('معمل ', '').replace('معامل ', '')
    text = text.replace('صيدلية ', '').replace('صيدليات ', '').replace('ص. ', '')
    text = text.replace('د. ', '').replace('د/', '').replace('دكتور ', '')
    text = text.replace('مركز ', '')
    # Remove content in parentheses
    text = text.split('(')[0].strip()
    return text.strip()

def find_facility_in_json(provided_facility, json_data):
    """Find if a facility exists in the JSON data"""
    name_ar = provided_facility.get('name_ar', '')
    normalized_provided = normalize_arabic(name_ar)
    
    for category in json_data.get('medical_directory', []):
        category_ar = category.get('category', {}).get('ar', '')
        category_en = category.get('category', {}).get('en', '')
        
        for facility in category.get('facilities', []):
            facility_name_ar = facility.get('name', {}).get('ar', '')
            facility_name_en = facility.get('name', {}).get('en', '')
            
            # Check exact match or normalized match
            if (name_ar in facility_name_ar or facility_name_ar in name_ar or
                normalized_provided in normalize_arabic(facility_name_ar) or
                normalize_arabic(facility_name_ar) in normalized_provided):
                return True, category_ar, facility_name_ar
            
            # Also check English name
            name_en = provided_facility.get('name_en', '')
            if name_en and (name_en.lower() in facility_name_en.lower() or 
                           facility_name_en.lower() in name_en.lower()):
                return True, category_ar, facility_name_ar
    
    return False, None, None

def main():
    base_dir = os.path.dirname(os.path.abspath(__file__))
    missing_data = {}
    all_found = True
    
    print("=" * 80)
    print("COMPARISON REPORT: Part 3 Data")
    print("=" * 80)
    print()
    
    for gov_name, categories in provided_data.items():
        json_file = os.path.join(base_dir, file_map[gov_name])
        
        if not os.path.exists(json_file):
            print(f"❌ File not found: {json_file}")
            continue
        
        with open(json_file, 'r', encoding='utf-8') as f:
            json_data = json.load(f)
        
        print("=" * 80)
        print(f"GOVERNORATE: {gov_name}")
        print("=" * 80)
        print()
        
        gov_missing = {}
        
        for category_name, facilities in categories.items():
            print(f"  Category: {category_name}")
            category_all_found = True
            
            for facility in facilities:
                found, found_category, found_name = find_facility_in_json(facility, json_data)
                
                if not found:
                    print(f"    ❌ MISSING: {facility['name_ar']} ({facility['name_en']})")
                    if category_name not in gov_missing:
                        gov_missing[category_name] = []
                    gov_missing[category_name].append(facility)
                    category_all_found = False
                    all_found = False
                else:
                    print(f"    ✅ Found: {facility['name_ar']} (in category: {found_category})")
            
            if category_all_found:
                print(f"    ✅ All facilities in '{category_name}' are present")
            print()
        
        if gov_missing:
            missing_data[gov_name] = gov_missing
    
    print("=" * 80)
    print("SUMMARY")
    print("=" * 80)
    print()
    
    if all_found:
        print("✅ All provided data exists in the JSON files!")
    else:
        print("❌ Missing data found:")
        print()
        for gov, categories in missing_data.items():
            print(f"Governorate: {gov}")
            for cat, facilities in categories.items():
                print(f"  Category: {cat}")
                for fac in facilities:
                    print(f"    - {fac['name_ar']} ({fac['name_en']})")
            print()
        
        # Save missing data to file
        output_file = os.path.join(base_dir, 'missing_part3_data.json')
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(missing_data, f, ensure_ascii=False, indent=2)
        print(f"Missing data saved to: {output_file}")

if __name__ == '__main__':
    main()

