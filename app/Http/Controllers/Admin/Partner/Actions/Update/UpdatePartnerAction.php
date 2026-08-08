<?php

namespace App\Http\Controllers\Admin\Partner\Actions\Update;

use App\Models\Partner;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdatePartnerAction
{
    private MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function execute(Partner $partner, array $validated): Partner
    {
        DB::beginTransaction();

        try {
            $partner->update([
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            if (isset($validated['image'])) {
                $partner->clearMediaCollection('image');
                $partner->addMedia($validated['image'])
                    ->toMediaCollection('image');
            }

            if (isset($validated['header_image'])) {
                $partner->clearMediaCollection('header_image');
                $partner->addMedia($validated['header_image'])
                    ->toMediaCollection('header_image');
            }

            if (!empty($validated['deleted_gallery_ids'])) {
                $this->mediaService->deleteMediaByIds($partner, $validated['deleted_gallery_ids'], 'gallery');
            }

            if (isset($validated['gallery']) && is_array($validated['gallery'])) {
                foreach ($validated['gallery'] as $image) {
                    $partner->addMedia($image)
                        ->toMediaCollection('gallery');
                }
            }

            $partner->refresh();

            DB::commit();

            Log::info('Partner updated successfully', [
                'partner_id' => $partner->id,
            ]);

            return $partner;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update partner', [
                'partner_id' => $partner->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
