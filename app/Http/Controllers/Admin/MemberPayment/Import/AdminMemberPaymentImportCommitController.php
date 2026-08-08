<?php

namespace App\Http\Controllers\Admin\MemberPayment\Import;

use App\Http\Controllers\Controller as BaseController;
use App\Models\MemberPayment;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminMemberPaymentImportCommitController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.membership_id' => ['required', 'integer', 'exists:memberships,id'],
            'rows.*.amount' => ['required', 'numeric', 'min:0'],
            'rows.*.type' => ['nullable', 'string', 'in:commission,profit,free'],
            'rows.*.months_paid' => ['required', 'integer', 'min:1'],
            'rows.*.from_date' => ['required', 'date'],
            'rows.*.to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $rows = $payload['rows'];
        $created = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                try {
                    $type = $row['type'] ?? 'commission';
                    $amount = $type === 'free' ? 0 : (float) $row['amount'];

                    MemberPayment::create([
                        'membership_id' => $row['membership_id'],
                        'amount' => $amount,
                        'type' => $type,
                        'months_paid' => (int) $row['months_paid'],
                        'from_date' => $row['from_date'],
                        'to_date' => $row['to_date'],
                        'created_by' => Auth::id(),
                    ]);

                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'index' => $i,
                        'membership_id' => $row['membership_id'] ?? null,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Some rows failed; nothing was saved.',
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payment import commit failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Import complete.',
            'created' => $created,
        ]);
    }
}
