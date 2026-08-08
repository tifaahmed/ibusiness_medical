<?php

namespace App\Http\Controllers\Admin\MembershipCard\Store;

use App\Http\Controllers\Admin\MembershipCard\Actions\Store\StoreMembershipCardAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\MembershipCard\StoreMembershipCardRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminMembershipCardStoreController extends BaseController
{
    public function __invoke(
        StoreMembershipCardRequest $request,
        StoreMembershipCardAction $action,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $result = $action->execute($validated, Auth::id());

            // JSON (not redirect) — the frontend needs the new card id and
            // the generated membership rows to render and upload the PDF
            // before navigating to the show page.
            return response()->json([
                'card' => [
                    'id' => $result['card']->id,
                    'batch_name' => $result['card']->batch_name,
                    'quantity' => $result['card']->quantity,
                    'start_number' => $result['card']->start_number,
                ],
                'memberships' => $result['memberships'],
                'upload_url' => route('admin.membership-card-patches.upload-pdf', $result['card']->id),
                'show_url' => route('admin.membership-card-patches.show', $result['card']->id),
            ]);
        } catch (\Throwable $e) {
            Log::error('StoreMembershipCard failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create membership card patches batch: ' . $e->getMessage(),
            ], 500);
        }
    }
}
