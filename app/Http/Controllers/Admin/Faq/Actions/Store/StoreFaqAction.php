<?php

namespace App\Http\Controllers\Admin\Faq\Actions\Store;

use App\Models\Faq;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreFaqAction
{
    public function execute(array $validated): Faq
    {
        DB::beginTransaction();

        try {
            $faq = Faq::create([
                'question' => $validated['question'],
                'answer' => $validated['answer'],
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('FAQ created successfully', [
                'faq_id' => $faq->id,
            ]);

            return $faq;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create FAQ', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
