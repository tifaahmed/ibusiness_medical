<?php

namespace App\Http\Controllers\Admin\Partner\Actions\Store;

use App\Models\Partner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StorePartnerAction
{
    public function execute(array $validated): Partner
    {
        DB::beginTransaction();

        try {
            $partner = Partner::create([
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if (isset($validated['image'])) {
                $partner->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            if (isset($validated['header_image'])) {
                $partner->addMedia($validated['header_image'])
                    ->toMediaCollection('header_image');
            }

            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                foreach ($validated['gallery'] as $image) {
                    $partner->addMedia($image)
                        ->toMediaCollection('gallery');
                }
            }

            DB::commit();

            Log::info('Partner created successfully', [
                'partner_id' => $partner->id,
            ]);

            return $partner;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create partner', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
