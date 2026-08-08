<?php

namespace App\Http\Controllers\Admin\Faq\Delete;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminFaqDeleteController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FAQS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FAQS; }

    public function __invoke(Request $request, Faq $faq): RedirectResponse
    {
        $this->assertOwns($faq);

        $faqId = $faq->id;

        try {
            DB::beginTransaction();

            $faq->delete();

            DB::commit();

            Log::info('FAQ deleted successfully', [
                'faq_id' => $faqId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.faq.list')
                ->with('success', 'FAQ deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete FAQ', [
                'faq_id' => $faqId,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete FAQ. Please try again.']);
        }
    }
}
