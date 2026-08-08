<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Actions\Store;

use App\Models\PartnerOffer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StorePartnerOfferAction
{
    public function execute(array $validated): PartnerOffer
    {
        DB::beginTransaction();

        try {
            $offer = PartnerOffer::create([
                'partner_id' => $validated['partner_id'],
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'old_price' => $validated['old_price'] ?? null,
                'new_price' => $validated['new_price'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'operator' => $validated['operator'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if (isset($validated['header_image'])) {
                $offer->addMedia($validated['header_image'])
                    ->toMediaCollection('header_image');
            }

            if (isset($validated['mobile_header_image'])) {
                $offer->addMedia($validated['mobile_header_image'])
                    ->toMediaCollection('mobile_header_image');
            }

            if (isset($validated['small_image'])) {
                $offer->addMedia($validated['small_image'])
                    ->toMediaCollection('small_image');
            }

            if (isset($validated['mobile_small_image'])) {
                $offer->addMedia($validated['mobile_small_image'])
                    ->toMediaCollection('mobile_small_image');
            }

            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                foreach ($validated['gallery'] as $image) {
                    $offer->addMedia($image)
                        ->toMediaCollection('gallery');
                }
            }

            DB::commit();

            Log::info('PartnerOffer created successfully', [
                'partner_offer_id' => $offer->id,
            ]);

            return $offer;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create partner offer', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
