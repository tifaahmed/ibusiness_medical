<?php

namespace App\Http\Controllers\Admin\User\Membership\Update;

use App\Http\Controllers\Admin\User\Membership\Actions\Address\UpsertMemberAddressAction;
use App\Http\Controllers\Admin\User\Membership\Actions\Update\UpdateUserMembershipAction;
use App\Http\Controllers\Admin\User\Membership\Actions\Update\SendMembershipUpdateNotificationAction;
use App\Http\Controllers\Concerns\ScopesByMembershipCreator;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\Admin\User\Membership\UpdateMembershipRequest;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminUserMembershipUpdateController extends BaseController
{
    use ScopesByMembershipCreator;


    private UpdateUserMembershipAction $updateAction;
    private SendMembershipUpdateNotificationAction $notificationAction;
    private UpsertMemberAddressAction $upsertAddressAction;
    private MediaService $mediaService;
    public function __construct(UpdateUserMembershipAction $updateAction, SendMembershipUpdateNotificationAction $notificationAction, UpsertMemberAddressAction $upsertAddressAction, MediaService $mediaService)
    {
        $this->updateAction = $updateAction;
        $this->notificationAction = $notificationAction;
        $this->upsertAddressAction = $upsertAddressAction;
        $this->mediaService = $mediaService;
    }       
    /**
     * Update the specified user and membership.
     */
    public function __invoke(
        UpdateMembershipRequest $request,
        string $user,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $user = User::with(['memberships' => function ($query) {
                $query->where('is_active', true);
            }])->where('slug', $user)->firstOrFail();

            $this->assertCanManageUser($user);

            // Execute the action to update user and membership in database
            $result = $this->updateAction->execute(
                $user,
                $validated,
                $request->hasFile('avatar') ? $request->file('avatar') : null,
                Auth::user(),
                $request,
            );

            $updatedUser = $result['user'];
            $membership = $result['membership'];
            $touchedMembership = $result['updatedMembership'] ?? $membership;
            $membershipWasCreated = $result['membershipWasCreated'];
            $passwordUpdated = $result['passwordUpdated'];

            // The member's primary address — governorate is the only required
            // field, the rest is optional courier detail. Written onto the
            // membership this edit actually touched.
            if ($touchedMembership) {
                try {
                    $this->upsertAddressAction->execute($touchedMembership, $validated, $request);
                } catch (\Exception $e) {
                    Log::error('Failed to save member address', [
                        'membership_id' => $touchedMembership->id,
                        'user_slug' => $updatedUser->slug,
                        'error_message' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            // Contract image — single file collection. Remove if asked, then
            // upload a replacement when one was provided.
            if ($touchedMembership) {
                if ($request->boolean('contract_image_remove')) {
                    try {
                        $touchedMembership->clearMediaCollection('contract');
                    } catch (\Exception $e) {
                        Log::error('Failed to clear contract image', [
                            'membership_id' => $touchedMembership->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                if ($request->hasFile('contract_image')) {
                    try {
                        $this->mediaService->uploadImage($touchedMembership, $request->file('contract_image'), 'contract');
                    } catch (\Exception $e) {
                        Log::error('Failed to upload contract image', [
                            'membership_id' => $touchedMembership->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Gallery — remove flagged items, then append new files.
                $removeIds = $request->input('gallery_remove_ids', []);
                if (is_array($removeIds) && count($removeIds) > 0) {
                    try {
                        $this->mediaService->deleteMediaByIds($touchedMembership, $removeIds, 'gallery');
                    } catch (\Exception $e) {
                        Log::error('Failed to remove gallery images', [
                            'membership_id' => $touchedMembership->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                if ($request->hasFile('gallery_images')) {
                    try {
                        $this->mediaService->appendImages($touchedMembership, (array) $request->file('gallery_images'), 'gallery');
                    } catch (\Exception $e) {
                        Log::error('Failed to upload gallery images', [
                            'membership_id' => $touchedMembership->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Execute the action to send notification (runs asynchronously via job)
            if ($membership) {
                $this->notificationAction->execute($updatedUser, $membership);
            }

            Log::info('User and membership updated successfully, notification action executed', [
                'user_id' => $updatedUser->id,
                'user_email' => $updatedUser->email,
                'membership_id' => $membership?->id,
                'membership_number' => $membership?->membership_number,
                'membership_created' => $membershipWasCreated,
                'password_updated' => $passwordUpdated,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->route('admin.user.membership.list')
                ->with('success', 'User and membership updated successfully. Notification email will be sent shortly.');
        } catch (\Exception $e) {
            Log::error('Failed to update user and membership', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'membership_number' => $validated['membership_number'] ?? null,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'Failed to update user and membership. Please try again.'])
                ->withInput();
        }
    }
}

