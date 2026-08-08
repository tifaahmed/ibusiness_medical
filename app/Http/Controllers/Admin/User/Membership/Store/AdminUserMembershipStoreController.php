<?php

namespace App\Http\Controllers\Admin\User\Membership\Store;


use App\Http\Controllers\Admin\User\Membership\Actions\Store\StoreUserMembershipAction;
use App\Http\Controllers\Admin\User\Membership\Actions\Store\SendMembershipNotificationAction;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\User\Membership\StoreMembershipRequest;
use App\Models\FamilyMember;
use App\Services\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class AdminUserMembershipStoreController extends BaseController
{
    private SendMembershipNotificationAction $notificationAction;
    private StoreUserMembershipAction $storeAction;
    private MediaService $mediaService;

    public function __construct(
        SendMembershipNotificationAction $notificationAction,
        StoreUserMembershipAction $storeAction,
        MediaService $mediaService
    ) {
        $this->notificationAction = $notificationAction;
        $this->storeAction = $storeAction;
        $this->mediaService = $mediaService;
    }
    /**
     * Store a newly created user with membership in storage.
     */
    public function __invoke(
        StoreMembershipRequest $request,
    ): RedirectResponse {
        try {
            \Log::info('=== Member Creation Request Started ===', [
                'request_data' => $request->except(['avatar', 'family_members']),
                'has_avatar' => $request->hasFile('avatar'),
                'has_family_members' => $request->has('family_members'),
            ]);

            $validated = $request->validated();

            \Log::info('Request validated', [
                'validated_keys' => array_keys($validated),
                'family_members_count' => isset($validated['family_members']) ? count($validated['family_members']) : 0,
            ]);

            // Execute the action to store user and membership in database (essential data only)
            $result = $this->storeAction->execute($validated, Auth::user(), $request);

            $user = $result['user'];
            $membership = $result['membership'];
            $plainPassword = $result['plainPassword'];

            // Handle avatar upload synchronously (immediately) - same as update flow
            if ($request->hasFile('avatar')) {
                try {
                    $avatarFile = $request->file('avatar');
                    $this->mediaService->uploadImage($user, $avatarFile, 'avatar');
                    \Log::info('Avatar uploaded successfully', [
                        'user_id' => $user->id,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to upload avatar', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                    ]);
                    // Don't throw - continue with other operations
                }
            }

            // Contract image — single file attached to the membership.
            if ($request->hasFile('contract_image')) {
                try {
                    $this->mediaService->uploadImage($membership, $request->file('contract_image'), 'contract');
                } catch (\Exception $e) {
                    \Log::error('Failed to upload contract image', [
                        'membership_id' => $membership->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Gallery — multiple files appended to the membership.
            if ($request->hasFile('gallery_images')) {
                try {
                    $this->mediaService->appendImages($membership, (array) $request->file('gallery_images'), 'gallery');
                } catch (\Exception $e) {
                    \Log::error('Failed to upload gallery images', [
                        'membership_id' => $membership->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create family members synchronously (immediately)
            if ($request->has('family_members') && is_array($request->input('family_members'))) {
                foreach ($request->input('family_members') as $index => $familyMember) {
                    try {
                        $familyMemberModel = FamilyMember::create([
                            'membership_id' => $membership->id,
                            'name' => $familyMember['name'] ?? '',
                            'relationship' => $familyMember['relationship'] ?? '',
                            'date_of_birth' => $familyMember['date_of_birth'] ?? null,
                            'phone' => $familyMember['phone'] ?? null,
                            'email' => $familyMember['email'] ?? null,
                            'is_active' => isset($familyMember['is_active']) ? (bool) $familyMember['is_active'] : true,
                        ]);

                        // Handle photo upload if provided
                        $photoKey = "family_members.{$index}.photo";
                        if ($request->hasFile($photoKey)) {
                            $photoFile = $request->file($photoKey);
                            $this->mediaService->uploadImage($familyMemberModel, $photoFile, 'photo');
                            \Log::info("Family member {$index} photo uploaded", [
                                'family_member_id' => $familyMemberModel->id,
                            ]);
                        }

                        \Log::info("Family member {$index} created successfully", [
                            'family_member_id' => $familyMemberModel->id,
                            'membership_id' => $membership->id,
                            'name' => $familyMemberModel->name,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error("Failed to create family member {$index}", [
                            'membership_id' => $membership->id,
                            'error' => $e->getMessage(),
                            'error_trace' => $e->getTraceAsString(),
                        ]);
                        // Continue with other family members even if one fails
                    }
                }
            }

            // Avatar and family members are now processed synchronously, no background job needed

            // Execute the action to send notification (runs asynchronously via job)
            $this->notificationAction->execute($user, $membership, $plainPassword);

            Log::info('User with membership created successfully, notification action executed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'membership_id' => $membership->id,
                'membership_number' => $membership->membership_number,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $redirectUrl = route('admin.user.membership.list');

            Log::info('=== Redirect Debug ===', [
                'redirect_url' => $redirectUrl,
                'app_url' => config('app.url'),
                'request_scheme' => $request->getScheme(),
                'request_secure' => $request->secure(),
                'is_https' => $request->isSecure(),
                'server_https' => $request->server('HTTPS'),
            ]);

            return redirect()->route('admin.user.membership.list')
                ->with('success', 'User with membership created successfully. Notification email will be sent shortly.');
        } catch (\Exception $e) {
            \Log::error('=== Member Creation Failed ===', [
                'email' => $validated['email'] ?? null,
                'membership_number' => $validated['membership_number'] ?? null,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return back()->withErrors(['error' => 'Failed to create user with membership: ' . $e->getMessage()])
                ->withInput();
        }
    }
}

