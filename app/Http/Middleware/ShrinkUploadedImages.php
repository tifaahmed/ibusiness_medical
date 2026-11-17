<?php

namespace App\Http\Middleware;

use App\Services\Images\ImageShrinker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploads (admin, member app, website, guest) may be ANY size: every image in the request is brought under 100 KB (no crop, see ImageShrinker) before the
 * controller, the validation `max:` rules or the media library ever see it. Files that are not images are left alone.
 */
class ShrinkUploadedImages
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->files->count() > 0) {
            $request->files->replace($this->walk($request->files->all()));
            // Laravel caches the converted files the first time they are asked for.
            (function () {
                $this->convertedFiles = null;
            })->call($request);
        }

        return $next($request);
    }

    private function walk(array $files): array
    {
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $files[$key] = $this->walk($file);
            } elseif ($file instanceof UploadedFile) {
                $files[$key] = ImageShrinker::shrink($file);
            }
        }

        return $files;
    }
}
