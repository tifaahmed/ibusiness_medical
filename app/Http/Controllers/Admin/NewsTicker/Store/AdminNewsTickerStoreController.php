<?php

namespace App\Http\Controllers\Admin\NewsTicker\Store;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\NewsTicker\StoreNewsTickerRequest;
use App\Models\NewsTicker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminNewsTickerStoreController extends BaseController
{
    public function __invoke(StoreNewsTickerRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $newsTicker = NewsTicker::create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'image_url' => $validated['image_url'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            if (array_key_exists('image', $validated) && $validated['image']) {
                $newsTicker->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            if (array_key_exists('mobile_image', $validated) && $validated['mobile_image']) {
                $newsTicker->addMedia($validated['mobile_image'])
                    ->toMediaCollection('mobile_image');
            }

            DB::commit();

            Log::info('News Ticker created successfully', [
                'news_ticker_id' => $newsTicker->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.news-ticker.list')
                ->with('success', 'News Ticker created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create News Ticker', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create News Ticker. Please try again.'])
                ->withInput();
        }
    }
}
