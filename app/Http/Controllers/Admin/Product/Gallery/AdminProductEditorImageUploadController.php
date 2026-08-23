<?php

namespace App\Http\Controllers\Admin\Product\Gallery;

use App\Http\Controllers\Controller as BaseController;
use App\Models\ProductGallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminProductEditorImageUploadController extends BaseController
{
    /**
     * Store an image dropped, pasted or picked inside the description editor.
     *
     * The file is written straight away so the editor can show it, but it is
     * only tied to the product — as a gallery row — when the form is saved,
     * which is also what makes this work on the create form.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $path = $request->file('image')->store(ProductGallery::EDITOR_DIRECTORY, 'public');

        Log::info('Product description image uploaded', [
            'path' => $path,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
