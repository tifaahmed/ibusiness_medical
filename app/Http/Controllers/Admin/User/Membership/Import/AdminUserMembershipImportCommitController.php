<?php

namespace App\Http\Controllers\Admin\User\Membership\Import;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminUserMembershipImportCommitController extends BaseController
{
    /**
     * Apply the (possibly edited) preview rows to the database.
     *
     * Modes:
     *  - "upsert": match each row by membership number → update if found, insert otherwise.
     *  - "clear":  soft-delete every existing user with memberships first, then insert all
     *              imported rows as brand-new members.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'mode' => ['required', 'in:upsert,clear'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.email' => ['nullable', 'email', 'max:255'],
            'rows.*.phone' => ['nullable', 'string', 'max:50'],
            'rows.*.membership_number' => ['required', 'string', 'max:255'],
            'rows.*.is_active' => ['nullable', 'boolean'],
            'rows.*.is_visible' => ['nullable', 'boolean'],
            'rows.*.job_title' => ['nullable', 'string', 'max:255'],
            'rows.*.company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'rows.*.partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'rows.*.registration_date' => ['nullable', 'date'],
            'rows.*.expiration_date' => ['required', 'date'],
            'rows.*.families' => ['nullable', 'array'],
            'rows.*.families.*.name' => ['required_with:rows.*.families', 'string', 'max:255'],
            'rows.*.families.*.relationship' => ['nullable', 'string'],
            'rows.*.families.*.date_of_birth' => ['nullable', 'date'],
            'rows.*.families.*.phone' => ['nullable', 'string', 'max:50'],
            'rows.*.families.*.email' => ['nullable', 'email', 'max:255'],
            'rows.*.families.*.is_active' => ['nullable', 'boolean'],
            'rows.*.payments' => ['nullable', 'array'],
            'rows.*.payments.*.amount' => ['required_with:rows.*.payments', 'numeric', 'min:0'],
            'rows.*.payments.*.months_paid' => ['required_with:rows.*.payments', 'integer', 'min:1'],
            'rows.*.payments.*.from_date' => ['required_with:rows.*.payments', 'date'],
            'rows.*.payments.*.to_date' => ['required_with:rows.*.payments', 'date'],
            'rows.*.payments.*.notes' => ['nullable', 'string'],
            'rows.*.payments.*.type' => ['nullable', 'string', 'max:20'],
        ]);

        $validRelationships = RelationshipEnum::values();

        $mode = $payload['mode'];
        $rows = $payload['rows'];

        // Membership numbers must be unique within the import payload itself.
        $numbers = array_column($rows, 'membership_number');
        $duplicates = array_filter(array_count_values($numbers), fn ($n) => $n > 1);
        if (! empty($duplicates)) {
            return response()->json([
                'message' => 'Duplicate membership numbers in import: '.implode(', ', array_keys($duplicates)),
            ], 422);
        }

        $created = 0;
        $updated = 0;
        $cleared = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            if ($mode === 'clear') {
                $cleared = User::whereHas('memberships')->whereNull('deleted_at')->count();
                // Soft-delete only — admins can restore from trash if this was a mistake.
                User::whereHas('memberships')->whereNull('deleted_at')->each(fn ($u) => $u->delete());
                Membership::whereNull('deleted_at')->each(fn ($m) => $m->delete());
                MemberPayment::query()->delete();
            }

            foreach ($rows as $i => $row) {
                try {
                    $email = ! empty($row['email']) ? mb_strtolower(trim($row['email'])) : null;
                    $jobTitle = ! empty($row['job_title'])
                        ? ['ar' => $row['job_title'], 'en' => $row['job_title']]
                        : null;

                    $existing = null;
                    if ($mode === 'upsert') {
                        $existing = Membership::with('user')
                            ->where('membership_number', $row['membership_number'])
                            ->first();
                        if (! $existing && $email) {
                            $user = User::where('email', $email)->first();
                            $existing = $user?->memberships()->orderBy('is_active', 'desc')->orderBy('created_at', 'desc')->first();
                        }
                    }

                    if ($existing) {
                        $user = $existing->user;
                        $user->update([
                            'name' => $row['name'],
                            'email' => $email,
                            'phone' => $row['phone'] ?? null,
                        ]);
                        $existing->update([
                            'membership_number' => $row['membership_number'],
                            'registration_date' => $row['registration_date'] ?? $existing->registration_date,
                            'expiration_date' => $row['expiration_date'],
                            'is_active' => (bool) ($row['is_active'] ?? true),
                            'is_visible' => (bool) ($row['is_visible'] ?? true),
                            'job_title' => $jobTitle,
                            'company_id' => $row['company_id'] ?? null,
                            'partner_id' => $row['partner_id'] ?? null,
                        ]);
                        $membership = $existing;
                        $updated++;
                    } else {
                        $user = User::create([
                            'name' => $row['name'],
                            'email' => $email,
                            'phone' => $row['phone'] ?? null,
                            'password' => Hash::make(Str::password(12)),
                        ]);
                        $membership = $user->memberships()->create([
                            'membership_number' => $row['membership_number'],
                            'registration_date' => $row['registration_date'] ?? now(),
                            'expiration_date' => $row['expiration_date'],
                            'is_active' => (bool) ($row['is_active'] ?? true),
                            'is_visible' => (bool) ($row['is_visible'] ?? true),
                            'job_title' => $jobTitle,
                            'company_id' => $row['company_id'] ?? null,
                            'partner_id' => $row['partner_id'] ?? null,
                            'created_by' => Auth::id(),
                        ]);
                        $created++;
                    }

                    $this->syncFamilyMembers($membership, $row['families'] ?? [], $validRelationships);
                    $this->syncPayments($membership, $row['payments'] ?? []);
                } catch (\Throwable $e) {
                    $errors[] = [
                        'index' => $i,
                        'membership_number' => $row['membership_number'] ?? null,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if (! empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Some rows failed; nothing was saved.',
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Membership import commit failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Import failed: '.$e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import complete.',
            'mode' => $mode,
            'created' => $created,
            'updated' => $updated,
            'cleared' => $cleared,
        ]);
    }

    /**
     * Upsert family members for a membership by name match (case-insensitive).
     * Existing rows not present in the import are left alone — admins delete
     * those manually from the edit page if needed.
     */
    private function syncFamilyMembers(Membership $membership, array $families, array $validRelationships): void
    {
        if (empty($families)) {
            return;
        }
        $existing = $membership->familyMembers()->get()->keyBy(fn ($f) => mb_strtolower(trim((string) $f->name)));

        foreach ($families as $f) {
            $name = trim((string) ($f['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            $relationship = mb_strtolower(trim((string) ($f['relationship'] ?? '')));
            if (! in_array($relationship, $validRelationships, true)) {
                $relationship = $validRelationships[0] ?? 'other';
            }
            $payload = [
                'name' => $name,
                'relationship' => $relationship,
                'date_of_birth' => $f['date_of_birth'] ?? null,
                'phone' => $f['phone'] ?? null,
                'email' => $f['email'] ?? null,
                'is_active' => isset($f['is_active']) ? (bool) $f['is_active'] : true,
            ];
            if (isset($existing[$key])) {
                $existing[$key]->update($payload);
            } else {
                $membership->familyMembers()->create($payload);
            }
        }
    }

    /**
     * Upsert payments for a membership by matching from_date + to_date.
     * Existing payments not present in the import are left alone.
     */
    private function syncPayments(Membership $membership, array $payments): void
    {
        if (empty($payments)) {
            return;
        }

        $existing = $membership->memberPayments()->get()->keyBy(
            fn ($p) => $p->from_date->format('Y-m-d').'|'.$p->to_date->format('Y-m-d')
        );

        foreach ($payments as $p) {
            $amount = $p['amount'] ?? null;
            $monthsPaid = $p['months_paid'] ?? null;
            $fromDate = $p['from_date'] ?? null;
            $toDate = $p['to_date'] ?? null;

            if ($amount === null || $monthsPaid === null || $fromDate === null || $toDate === null) {
                continue;
            }

            $key = $fromDate.'|'.$toDate;
            $payload = [
                'amount' => $amount,
                'months_paid' => $monthsPaid,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'notes' => $p['notes'] ?? null,
                'type' => $p['type'] ?? 'commission',
                'created_by' => Auth::id(),
            ];

            if (isset($existing[$key])) {
                $existing[$key]->update($payload);
            } else {
                $membership->memberPayments()->create($payload);
            }
        }
    }
}
