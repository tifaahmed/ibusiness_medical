<?php

namespace App\Http\Controllers\Admin\Faq\Update;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Admin\Faq\Actions\Update\UpdateFaqAction;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\Faq\UpdateFaqRequest;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class AdminFaqUpdateController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FAQS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FAQS; }

    private UpdateFaqAction $updateAction;

    public function __construct(UpdateFaqAction $updateAction)
    {
        $this->updateAction = $updateAction;
    }

    public function __invoke(UpdateFaqRequest $request, Faq $faq): RedirectResponse
    {
        $this->assertOwns($faq);

        $validated = $request->validated();

        try {
            $updatedFaq = $this->updateAction->execute($faq, $validated);

            Log::info('FAQ updated successfully', [
                'faq_id' => $updatedFaq->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.faq.list')
                ->with('success', 'FAQ updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update FAQ', [
                'faq_id' => $faq->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return back()->withErrors(['error' => 'Failed to update FAQ. Please try again.'])
                ->withInput();
        }
    }
}
