<?php

namespace App\Http\Controllers\Admin\Tag\Actions\Update;

use App\Models\Tag;
use Illuminate\Support\Facades\Log;

class UpdateTagAction
{
    public function execute(Tag $tag, array $validated): Tag
    {
        $updates = [];

        if (isset($validated['name'])) {
            $updates['name'] = $validated['name'];
        }
        if (array_key_exists('icon', $validated)) {
            $updates['icon'] = $validated['icon'];
        }
        if (array_key_exists('color', $validated)) {
            $updates['color'] = $validated['color'];
        }

        if (!empty($updates)) {
            $tag->update($updates);
        }

        Log::info('Tag updated', ['id' => $tag->id]);

        return $tag->refresh();
    }
}
