<?php

namespace App\Http\Controllers\Admin\NewsTicker\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\NewsTicker\UpdateNewsTickerRequest;
use App\Models\NewsTicker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminNewsTickerUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_NEWS_TICKERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_NEWS_TICKERS; }

    public function __invoke(UpdateNewsTickerRequest $request, NewsTicker $newsTicker): RedirectResponse
    {
        $this->assertOwns($newsTicker);

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $newsTicker->update([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'image_url' => $validated['image_url'] ?? $newsTicker->image_url,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if ($validated['image'] ?? false) {
                $newsTicker->clearMediaCollection('image');
                $newsTicker->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            if ($validated['mobile_image'] ?? false) {
                $newsTicker->clearMediaCollection('mobile_image');
                $newsTicker->addMedia($validated['mobile_image'])
                    ->toMediaCollection('mobile_image');
            }

            DB::commit();

            Log::info('News Ticker updated successfully', [
                'news_ticker_id' => $newsTicker->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.news-ticker.list')
                ->with('success', 'News Ticker updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update News Ticker', [
                'news_ticker_id' => $newsTicker->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update News Ticker. Please try again.'])
                ->withInput();
        }
    }
}
