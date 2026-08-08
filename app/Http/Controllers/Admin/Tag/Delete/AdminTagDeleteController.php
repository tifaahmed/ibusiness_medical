<?php

namespace App\Http\Controllers\Admin\Tag\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminTagDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __invoke(int $tag): RedirectResponse
    {
        $tag = Tag::findOrFail($tag);
        $this->assertOwns($tag);

        try {
            $tag->delete();
            Log::info('Tag deleted', ['id' => $tag->id]);
            return redirect()->route('admin.tag.list')->with('success', 'Tag deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete tag. Please try again.']);
        }
    }
}
