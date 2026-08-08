<?php

namespace App\Jobs\Admin\User\Membership;

use App\Models\User;
use App\Models\Membership;
use App\Models\FamilyMember;
use App\Services\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessMembershipSecondaryDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [10, 30, 60];

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $userId,
        public int $membershipId,
        public ?string $avatarPath = null,
        public array $familyMembersData = []
    ) {
        $this->afterCommit();
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(MediaService $mediaService): void
    {
        $user = User::find($this->userId);
        $membership = Membership::find($this->membershipId);

        if (!$user || !$membership) {
            Log::error('ProcessMembershipSecondaryDataJob: User or Membership not found', [
                'user_id' => $this->userId,
                'membership_id' => $this->membershipId,
            ]);
            return;
        }

        Log::info('ProcessMembershipSecondaryDataJob: Starting', [
            'user_id' => $this->userId,
            'membership_id' => $this->membershipId,
            'has_avatar' => $this->avatarPath !== null,
            'family_members_count' => count($this->familyMembersData),
        ]);

        // Process avatar upload
        if ($this->avatarPath) {
            $this->processAvatar($user, $mediaService);
        }

        // Process family members
        if (!empty($this->familyMembersData)) {
            $this->processFamilyMembers($membership, $mediaService);
        }

        // Clean up temp files
        $this->cleanupTempFiles();

        Log::info('ProcessMembershipSecondaryDataJob: Completed', [
            'user_id' => $this->userId,
            'membership_id' => $this->membershipId,
        ]);
    }

    /**
     * Process avatar upload.
     */
    private function processAvatar(User $user, MediaService $mediaService): void
    {
        try {
            if (Storage::disk('local')->exists($this->avatarPath)) {
                $fullPath = Storage::disk('local')->path($this->avatarPath);
                $file = new \Illuminate\Http\File($fullPath);

                $mediaService->uploadImageFromPath($user, $fullPath, 'avatar');

                Log::info('Avatar uploaded successfully', ['user_id' => $user->id]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to upload avatar', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - continue with other operations
        }
    }

    /**
     * Process family members creation.
     */
    private function processFamilyMembers(Membership $membership, MediaService $mediaService): void
    {
        foreach ($this->familyMembersData as $index => $familyMemberData) {
            try {
                $familyMember = FamilyMember::create([
                    'membership_id' => $membership->id,
                    'name' => $familyMemberData['name'],
                    'relationship' => $familyMemberData['relationship'],
                    'date_of_birth' => $familyMemberData['date_of_birth'] ?? null,
                    'phone' => $familyMemberData['phone'] ?? null,
                    'email' => $familyMemberData['email'] ?? null,
                    'is_active' => $familyMemberData['is_active'] ?? true,
                ]);

                // Handle photo upload if provided
                if (!empty($familyMemberData['photo_path']) && Storage::disk('local')->exists($familyMemberData['photo_path'])) {
                    $fullPath = Storage::disk('local')->path($familyMemberData['photo_path']);
                    $mediaService->uploadImageFromPath($familyMember, $fullPath, 'photo');
                }

                Log::info("Family member {$index} created successfully", [
                    'family_member_id' => $familyMember->id,
                    'membership_id' => $membership->id,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to create family member {$index}", [
                    'membership_id' => $membership->id,
                    'error' => $e->getMessage(),
                    'data' => array_diff_key($familyMemberData, ['photo_path' => '']),
                ]);
                // Don't throw - continue with other family members
            }
        }
    }

    /**
     * Clean up temporary files.
     */
    private function cleanupTempFiles(): void
    {
        try {
            if ($this->avatarPath && Storage::disk('local')->exists($this->avatarPath)) {
                Storage::disk('local')->delete($this->avatarPath);
            }

            foreach ($this->familyMembersData as $familyMemberData) {
                if (!empty($familyMemberData['photo_path']) && Storage::disk('local')->exists($familyMemberData['photo_path'])) {
                    Storage::disk('local')->delete($familyMemberData['photo_path']);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup temp files', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('ProcessMembershipSecondaryDataJob: Job failed permanently', [
            'user_id' => $this->userId,
            'membership_id' => $this->membershipId,
            'error' => $exception?->getMessage(),
        ]);

        // Clean up temp files even on failure
        $this->cleanupTempFiles();

        // Optionally notify admin about the failure
        // AdminNotification::dispatch(...);
    }
}
