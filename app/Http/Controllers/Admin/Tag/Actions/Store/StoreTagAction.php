<?php

namespace App\Http\Controllers\Admin\Tag\Actions\Store;

use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StoreTagAction
{
    public function execute(array $validated): Tag
    {
        $tag = Tag::create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
            'created_by' => Auth::id(),
        ]);

        Log::info('Tag created', ['id' => $tag->id]);

        return $tag;
    }
}
