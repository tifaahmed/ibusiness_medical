<?php

use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

// Shared default images configuration
$GLOBALS['_default_images'] = [
    'avatar' => '/images/defaults/avatar.png',
    'default' => '/images/defaults/default.jpg',
];

if (!function_exists('get_image_url')) {
    function get_image_url(?HasMedia $model, string $collection = 'avatar', ?string $customDefault = null): string
    {
        global $_default_images;

        try {
            if (!$model) {
                return $customDefault ?? ($_default_images[$collection] ?? $_default_images['default']);
            }

            $media = null;
            try {
                $media = $model->getMedia($collection)->first();
            } catch (\Exception $e) {
                Log::error('ImageHelper: Failed to get media from collection', [
                    'model' => get_class($model),
                    'model_id' => $model->id ?? null,
                    'collection' => $collection,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return $customDefault ?? ($_default_images[$collection] ?? $_default_images['default']);
            }

            if ($media instanceof Media) {
                try {
                    // Get the URL from Spatie MediaLibrary
                    $url = $media->getUrl();
                    
                    // Convert absolute URLs to relative URLs for better compatibility
                    // This ensures URLs work regardless of the APP_URL configuration
                    $appUrl = config('app.url');
                    if ($appUrl && str_starts_with($url, $appUrl)) {
                        $url = substr($url, strlen($appUrl));
                    }
                    
                    // Ensure the URL starts with / if it's relative
                    if (!str_starts_with($url, '/') && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                        $url = '/' . $url;
                    }
                    
                    Log::debug('ImageHelper: Successfully retrieved image URL', [
                        'model' => get_class($model),
                        'model_id' => $model->id ?? null,
                        'collection' => $collection,
                        'url' => $url,
                    ]);
                    return $url;
                } catch (\Exception $e) {
                    Log::error('ImageHelper: Failed to get URL from media', [
                        'model' => get_class($model),
                        'model_id' => $model->id ?? null,
                        'collection' => $collection,
                        'media_id' => $media->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    return $customDefault ?? ($_default_images[$collection] ?? $_default_images['default']);
                }
            }

            return $customDefault ?? ($_default_images[$collection] ?? $_default_images['default']);
        } catch (\Exception $e) {
            Log::error('ImageHelper: Unexpected error in get_image_url', [
                'model' => $model ? get_class($model) : null,
                'model_id' => $model->id ?? null,
                'collection' => $collection,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $customDefault ?? ($_default_images[$collection] ?? $_default_images['default']);
        }
    }
}

if (!function_exists('set_default_image')) {
    function set_default_image(string $collection, string $imagePath): void
    {
        try {
            global $_default_images;
            $_default_images[$collection] = $imagePath;
            
            Log::info('ImageHelper: Default image set', [
                'collection' => $collection,
                'image_path' => $imagePath,
            ]);
        } catch (\Exception $e) {
            Log::error('ImageHelper: Failed to set default image', [
                'collection' => $collection,
                'image_path' => $imagePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
