<?php

namespace App\Http\Controllers\Admin\NewsTicker\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\NewsTicker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminNewsTickerDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_NEWS_TICKERS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_NEWS_TICKERS; }

    public function __invoke(Request $request, NewsTicker $newsTicker): RedirectResponse
    {
        $this->assertOwns($newsTicker);

        $newsTickerId = $newsTicker->id;

        try {
            DB::beginTransaction();

            $newsTicker->delete();

            DB::commit();

            Log::info('News Ticker deleted successfully', [
                'news_ticker_id' => $newsTickerId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.news-ticker.list')
                ->with('success', 'News Ticker deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete News Ticker', [
                'news_ticker_id' => $newsTickerId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete News Ticker. Please try again.']);
        }
    }
}
