<?php

namespace App\Http\Controllers\Admin\Tag\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Tag\Actions\Update\UpdateTagAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Tag\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminTagUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_SERVICES; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_SERVICES; }

    public function __construct(private UpdateTagAction $updateAction) {}

    public function __invoke(UpdateTagRequest $request, int $tag): RedirectResponse
    {
        try {
            $tag = Tag::findOrFail($tag);
            $this->assertOwns($tag);

            $updated = $this->updateAction->execute($tag, $request->validated());
            Log::info('Tag updated', ['id' => $updated->id, 'ip' => $request->ip()]);
            return redirect()->route('admin.tag.list')->with('success', 'Tag updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update tag. Please try again.'])->withInput();
        }
    }
}
