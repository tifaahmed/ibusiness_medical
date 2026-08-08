<?php

namespace App\Http\Controllers\Admin\Faq\Actions\Update;

use App\Models\Faq;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateFaqAction
{
    public function execute(Faq $faq, array $validated): Faq
    {
        DB::beginTransaction();

        try {
            $faq->update([
                'question' => $validated['question'],
                'answer' => $validated['answer'],
                'is_active' => $validated['is_active'] ?? $faq->is_active,
                'sort_order' => $validated['sort_order'] ?? $faq->sort_order,
            ]);

            $faq->refresh();

            DB::commit();

            Log::info('FAQ updated successfully', [
                'faq_id' => $faq->id,
            ]);

            return $faq;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update FAQ', [
                'faq_id' => $faq->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
