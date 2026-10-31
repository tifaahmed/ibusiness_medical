<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransfer;
use App\Support\MemberTransfers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Members sending points or wallet money to each other, with the recipient's agreement — see `App\Support\MemberTransfers`.
 * The recipient is picked by phone or card number; the app shows a masked name first so the sender can check it is the right person.
 */
class TransferController extends Controller
{
    /** The sender checks WHO they are about to ask: a first name and the last two digits of the phone. */
    public function lookup(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:32'], 'card' => ['nullable', 'string', 'max:32']]);

        $found = MemberTransfers::find($data['phone'] ?? null, $data['card'] ?? null, $member->id);

        return $found === null
            ? response()->json(['success' => false, 'reason' => 'not_found', 'message' => 'No member found.'], 404)
            : response()->json(['success' => true, 'data' => ['name' => MemberTransfers::masked($found)]]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        $rows = WalletTransfer::query()->with(['from:id,name,phone', 'to:id,name,phone'])
            ->where(fn ($q) => $q->where('from_user_id', $member->id)->orWhere('to_user_id', $member->id))
            ->latest('id')->limit(100)->get();

        return response()->json(['success' => true, 'data' => [
            'enabled' => MemberTransfers::enabled(),
            'transfers' => $rows->map(fn (WalletTransfer $t) => $this->present($t, $member))->all(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:32', 'required_without:card'],
            'card' => ['nullable', 'string', 'max:32', 'required_without:phone'],
            'kind' => ['required', 'in:points,wallet'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'id' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $result = MemberTransfers::request($member, $data['phone'] ?? null, $data['card'] ?? null, $data['kind'], (float) $data['amount'], $data['id'], $data['note'] ?? null);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'reason' => $result['reason'], 'message' => 'The transfer request could not be made.'], 422);
        }

        return response()->json(['success' => true, 'data' => ['duplicate' => $result['duplicate'] ?? false, 'transfer' => $this->present($result['transfer']->load(['from:id,name,phone', 'to:id,name,phone']), $member)]]);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        /** @var User $member */
        $member = $request->user();

        try {
            $result = MemberTransfers::accept($member, $id);
        } catch (\Throwable $e) {
            Log::error('Member transfer failed', ['user_id' => $member->id, 'transfer_id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Could not complete the transfer.'], 500);
        }

        if (! $result['ok']) {
            return response()->json(['success' => false, 'reason' => $result['reason']], $result['reason'] === 'not_found' ? 404 : 422);
        }

        return response()->json(['success' => true, 'data' => ['transfer' => $this->present($result['transfer']->load(['from:id,name,phone', 'to:id,name,phone']), $member)]]);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        return $this->close($request, $id, WalletTransfer::DECLINED);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        return $this->close($request, $id, WalletTransfer::CANCELLED);
    }

    private function close(Request $request, int $id, string $as): JsonResponse
    {
        return MemberTransfers::close($request->user(), $id, $as)
            ? response()->json(['success' => true])
            : response()->json(['success' => false, 'reason' => 'not_open'], 422);
    }

    /** @return array<string, mixed> */
    private function present(WalletTransfer $t, User $viewer): array
    {
        $mine = $t->from_user_id === $viewer->id;
        $other = $mine ? $t->to : $t->from;
        $open = $t->isOpen();

        return [
            'id' => $t->id,
            'direction' => $mine ? 'out' : 'in',
            'kind' => $t->kind,
            'amount' => $t->kind === WalletTransfer::KIND_POINTS ? (int) $t->amount : (float) $t->amount,
            'status' => $t->status === WalletTransfer::PENDING && ! $open ? WalletTransfer::EXPIRED : $t->status,
            'other' => $other ? MemberTransfers::masked($other) : null,
            'note' => $t->note,
            'expires_at' => $t->expires_at?->toIso8601String(),
            'at' => $t->created_at?->toIso8601String(),
        ];
    }
}
