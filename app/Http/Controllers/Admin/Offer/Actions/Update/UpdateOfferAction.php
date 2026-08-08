<?php

namespace App\Http\Controllers\Admin\Offer\Actions\Update;

use App\Models\Offer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateOfferAction
{
    /**
     * Execute the action to update an offer.
     *
     * @param Offer $offer
     * @param array $validated
     * @return Offer
     * @throws \Exception
     */
    public function execute(Offer $offer, array $validated): Offer
    {
        DB::beginTransaction();

        try {
            // Update the offer
            $offer->update([
                'offerable_type' => $validated['offerable_type'],
                'offerable_id' => $validated['offerable_id'],
                'title' => $validated['title'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'full_description' => $validated['full_description'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'price' => $validated['price'] ?? null,
                'old_price' => $validated['old_price'] ?? null,
            ]);

            // Handle image upload
            if (isset($validated['image'])) {
                $offer->clearMediaCollection('image');
                $offer->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }
            if (isset($validated['mobile_image'])) {
                $offer->clearMediaCollection('mobile_image');
                $offer->addMedia($validated['mobile_image'])
                    ->toMediaCollection('mobile_image');
            }

            // Handle thumbnail upload
            if (isset($validated['thumbnail'])) {
                $offer->clearMediaCollection('thumbnail');
                $offer->addMedia($validated['thumbnail'])
                    ->toMediaCollection('thumbnail');
            }
            if (isset($validated['mobile_thumbnail'])) {
                $offer->clearMediaCollection('mobile_thumbnail');
                $offer->addMedia($validated['mobile_thumbnail'])
                    ->toMediaCollection('mobile_thumbnail');
            }

            $offer->refresh();

            DB::commit();

            Log::info('Offer updated successfully', [
                'offer_id' => $offer->id,
                'offer_slug' => $offer->slug,
            ]);

            return $offer;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update offer', [
                'offer_id' => $offer->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
