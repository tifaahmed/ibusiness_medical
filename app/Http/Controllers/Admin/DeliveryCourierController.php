<?php

namespace App\Http\Controllers\Admin;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\DeliveryCourier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The couriers who hand membership cards to members. A courier with history is deactivated, never deleted. */
class DeliveryCourierController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(UserPermissionEnum::VIEW_CARD_DELIVERIES), 403);

        return Inertia::render('Admin/CardDeliveries/Couriers', [
            'couriers' => DeliveryCourier::query()->withCount('deliveries')->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'notes' => $c->notes, 'is_active' => $c->is_active, 'deliveries_count' => $c->deliveries_count])->all(),
            'canManage' => $request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        DeliveryCourier::create($this->data($request) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Courier added.');
    }

    public function update(Request $request, DeliveryCourier $courier): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        $courier->update($this->data($request));

        return back()->with('success', 'Courier updated.');
    }

    public function destroy(Request $request, DeliveryCourier $courier): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermissionEnum::MANAGE_CARD_DELIVERIES), 403);
        if ($courier->deliveries()->exists()) {
            return back()->with('error', 'This courier has deliveries — deactivate him instead of deleting.');
        }
        $courier->delete();

        return back()->with('success', 'Courier deleted.');
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
