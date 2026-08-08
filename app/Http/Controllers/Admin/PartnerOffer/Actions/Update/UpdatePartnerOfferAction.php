<?php

namespace App\Http\Controllers\Admin\PartnerOffer\Actions\Update;

use App\Models\PartnerOffer;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdatePartnerOfferAction
{
    private MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function execute(PartnerOffer $offer, array $validated): PartnerOffer
    {
        DB::beginTransaction();

        try {
            $offer->update([
                'partner_id' => $validated['partner_id'],
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'old_price' => $validated['old_price'] ?? null,
                'new_price' => $validated['new_price'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'operator' => $validated['operator'] ?? null,
            ]);

            if (isset($validated['header_image'])) {
                $offer->clearMediaCollection('header_image');
                $offer->addMedia($validated['header_image'])
                    ->toMediaCollection('header_image');
            }

            if (isset($validated['mobile_header_image'])) {
                $offer->clearMediaCollection('mobile_header_image');
                $offer->addMedia($validated['mobile_header_image'])
                    ->toMediaCollection('mobile_header_image');
            }

            if (isset($validated['small_image'])) {
                $offer->clearMediaCollection('small_image');
                $offer->addMedia($validated['small_image'])
                    ->toMediaCollection('small_image');
            }

            if (isset($validated['mobile_small_image'])) {
                $offer->clearMediaCollection('mobile_small_image');
                $offer->addMedia($validated['mobile_small_image'])
                    ->toMediaCollection('mobile_small_image');
            }

            if (!empty($validated['deleted_gallery_ids'])) {
                $this->mediaService->deleteMediaByIds($offer, $validated['deleted_gallery_ids'], 'gallery');
            }

            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                foreach ($validated['gallery'] as $image) {
                    $offer->addMedia($image)
                        ->toMediaCollection('gallery');
                }
            }

            $offer->refresh();

            DB::commit();

            Log::info('PartnerOffer updated successfully', [
                'partner_offer_id' => $offer->id,
            ]);

            return $offer;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update partner offer', [
                'partner_offer_id' => $offer->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
