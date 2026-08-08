<?php

namespace App\Http\Controllers\Admin\Tag\Store;

use App\Http\Controllers\Admin\Tag\Actions\Store\StoreTagAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Tag\StoreTagRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminTagStoreController extends BaseController
{
    public function __construct(private StoreTagAction $storeAction) {}

    public function __invoke(StoreTagRequest $request): RedirectResponse
    {
        try {
            $tag = $this->storeAction->execute($request->validated());
            Log::info('Tag store request', ['id' => $tag->id, 'ip' => $request->ip()]);
            return redirect()->route('admin.tag.list')->with('success', 'Tag created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create tag. Please try again.'])->withInput();
        }
    }
}
