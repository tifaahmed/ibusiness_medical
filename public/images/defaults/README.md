# Default Images Directory

This directory contains default placeholder images that will be used when media files are not found.

## Required Default Images

Please add the following default images to this directory:

- `avatar.png` - Default avatar image
- `profile.png` - Default profile image
- `document.png` - Default document image
- `membership.png` - Default membership image
- `default.png` - Generic default image (fallback)

## Image Specifications

- Recommended format: PNG with transparency
- Recommended size: 200x200px for avatars, 400x400px for profiles
- File size: Keep under 100KB for optimal performance

## Usage

The ImageHelper will automatically use these images when:
1. A model doesn't have media in the specified collection
2. The media file doesn't exist or is inaccessible

You can customize default images by calling:
```php
ImageHelper::setDefaultImage('collection_name', '/custom/path/to/image.png');
```


