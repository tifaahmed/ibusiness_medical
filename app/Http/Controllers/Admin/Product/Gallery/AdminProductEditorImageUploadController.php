<?php

namespace App\Http\Controllers\Admin\Product\Gallery;

use App\Http\Controllers\Controller as BaseController;
use App\Support\EditorImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Store an image dropped, pasted or picked inside any description editor
 * (product, store or facility — `scope`).
 *
 * The file is written straight away so the editor can show it, but it is only
 * tied to the record — as a hidden gallery row — when the form is saved, which
 * is also what makes this work on the create forms. The URL handed back is
 * host-less ("/storage/…"), which is what ends up in the description.
 */
class AdminProductEditorImageUploadController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'scope' => ['nullable', 'in:'.implode(',', array_keys(EditorImages::DIRECTORIES))],
        ]);

        try {
            $path = $request->file('image')->store(EditorImages::directory($validated['scope'] ?? 'product'), 'public');
        } catch (\Throwable $e) {
            Log::error('Description image upload failed', [
                'scope' => $validated['scope'] ?? 'product',
                'user_id' => $request->user()?->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'The image could not be stored.'], 500);
        }

        Log::info('Description image uploaded', [
            'scope' => $validated['scope'] ?? 'product',
            'path' => $path,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['path' => $path, 'url' => EditorImages::url($path)]);
    }
}
