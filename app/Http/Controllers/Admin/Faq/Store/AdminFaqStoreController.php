<?php

namespace App\Http\Controllers\Admin\Faq\Store;

use App\Http\Controllers\Admin\Faq\Actions\Store\StoreFaqAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Faq\StoreFaqRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminFaqStoreController extends BaseController
{
    private StoreFaqAction $storeAction;

    public function __construct(StoreFaqAction $storeAction)
    {
        $this->storeAction = $storeAction;
    }

    public function __invoke(StoreFaqRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $faq = $this->storeAction->execute($validated);

            Log::info('FAQ created successfully', [
                'faq_id' => $faq->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.faq.list')
                ->with('success', 'FAQ created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create FAQ', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to create FAQ. Please try again.'])
                ->withInput();
        }
    }
}
