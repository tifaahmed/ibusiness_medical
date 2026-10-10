<?php

namespace App\Http\Controllers\Admin\Tag\Quick;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Tag\Actions\Store\StoreTagAction;
use App\Http\Controllers\Admin\Tag\Actions\Update\UpdateTagAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Tag\StoreTagRequest;
use App\Http\Requests\Admin\Tag\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Create / edit a tag from a popup on another form (the store form), answering
 * JSON instead of redirecting. Same requests and actions as the tag pages.
 */
class AdminTagQuickController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_TAGS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_TAGS; }

    public function store(StoreTagRequest $request, StoreTagAction $action): JsonResponse
    {
        try {
            return response()->json(['tag' => $this->shape($action->execute($request->validated()))], 201);
        } catch (\Throwable $e) {
            Log::error('Quick tag create failed', ['error_message' => $e->getMessage(), 'error_trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Failed to create the tag. Please try again.'], 500);
        }
    }

    public function update(UpdateTagRequest $request, Tag $tag, UpdateTagAction $action): JsonResponse
    {
        $this->assertOwns($tag);

        try {
            return response()->json(['tag' => $this->shape($action->execute($tag, $request->validated()))]);
        } catch (\Throwable $e) {
            Log::error('Quick tag update failed', ['tag_id' => $tag->id, 'error_message' => $e->getMessage(), 'error_trace' => $e->getTraceAsString()]);

            return response()->json(['message' => 'Failed to update the tag. Please try again.'], 500);
        }
    }

    /** Same shape as Tag::forPicker() rows, plus what the popup needs to edit it. */
    private function shape(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'name_translations' => $tag->getTranslations('name'),
            'icon' => $tag->icon,
            'color' => $tag->color,
            'applies_to' => $tag->applies_to ?? [],
        ];
    }
}
