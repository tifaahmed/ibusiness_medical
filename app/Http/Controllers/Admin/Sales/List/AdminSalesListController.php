<?php

namespace App\Http\Controllers\Admin\Sales\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Sales\List\AdminSalesListCollection;
use App\Models\Sales;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSalesListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_SALES;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_SALES;
    }

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $sales = Sales::query()
            ->with([
                'facilities:id,sales_id,name,slug,facility_type_id',
                'facilities.facilityType:id,name',
                'adminUser:id,name,email',
                'creator:id,name',
            ])
            ->withCount(['facilities', 'members', 'stores'])
            ->tap(fn ($q) => $this->applyCreatorScope($q))
            ->tap(fn ($q) => $this->applyFilters($q, $filters))
            ->tap(fn ($q) => $this->applySort($q, $filters['sort']))
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Sales/List', [
            'sales' => (new AdminSalesListCollection($sales))->toArray($request),
            'filters' => $filters,
            'filterOptions' => [
                // Only admins who ARE linked to a rep: picking anyone else could only ever return nothing.
                'admins' => Sales::query()->whereNotNull('admin_user_id')->with('adminUser:id,name,email')->get()
                    ->map(fn (Sales $s) => ['value' => $s->admin_user_id, 'label' => trim(($s->adminUser?->name ?? '#'.$s->admin_user_id).' — '.($s->adminUser?->email ?? ''), ' —')])
                    ->sortBy('label')->values()->all(),
                'creators' => $this->scopesToCreator() ? [] : \App\Models\User::query()
                    ->whereIn('id', Sales::query()->whereNotNull('created_by')->select('created_by'))
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            ],
        ]);
    }

    private const YES_NO = ['yes', 'no'];

    protected function getFilters(Request $request): array
    {
        $yn = fn (string $key) => in_array($request->input($key), self::YES_NO, true) ? $request->input($key) : '';

        return [
            'search' => trim((string) $request->input('search', '')),
            'admin' => $yn('admin'),                                   // has a linked admin account
            'admin_id' => $request->filled('admin_id') ? (int) $request->input('admin_id') : null, // THIS admin
            'has_image' => $yn('has_image'),
            'has_facilities' => $yn('has_facilities'),
            'has_members' => $yn('has_members'),
            'has_stores' => $yn('has_stores'),
            'created_by' => $request->filled('created_by') ? (int) $request->input('created_by') : null,
            'created_from' => $this->date($request->input('created_from')),
            'created_to' => $this->date($request->input('created_to')),
            'sort' => in_array($request->input('sort'), ['newest', 'oldest', 'name', 'facilities', 'members', 'stores'], true) ? $request->input('sort') : 'newest',
        ];
    }

    private function date(mixed $v): string
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }

    private function applyFilters($q, array $f): void
    {
        $q->when($f['search'] !== '', function ($q) use ($f) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $f['search']).'%';
            // The rep's name (both languages live in the one column) or the linked admin's name / e-mail.
            $q->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhereHas('adminUser', fn ($a) => $a->where('name', 'like', $like)->orWhere('email', 'like', $like)));
        });

        $q->when($f['admin'] === 'yes', fn ($q) => $q->whereNotNull('admin_user_id'))
            ->when($f['admin'] === 'no', fn ($q) => $q->whereNull('admin_user_id'))
            ->when($f['admin_id'], fn ($q, $id) => $q->where('admin_user_id', $id))
            ->when($f['created_by'], fn ($q, $id) => $q->where('created_by', $id))
            ->when($f['created_from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $f['created_from']))
            ->when($f['created_to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $f['created_to']));

        // has image: a media row in the `image` collection
        $q->when($f['has_image'] !== '', fn ($q) => $f['has_image'] === 'yes'
            ? $q->whereHas('media', fn ($m) => $m->where('collection_name', 'image'))
            : $q->whereDoesntHave('media', fn ($m) => $m->where('collection_name', 'image')));

        foreach (['has_facilities' => 'facilities', 'has_members' => 'members', 'has_stores' => 'stores'] as $key => $relation) {
            $q->when($f[$key] === 'yes', fn ($q) => $q->has($relation))
                ->when($f[$key] === 'no', fn ($q) => $q->doesntHave($relation));
        }
    }

    private function applySort($q, string $sort): void
    {
        match ($sort) {
            'oldest' => $q->orderBy('id'),
            'name' => $q->orderBy('name')->orderByDesc('id'),
            'facilities' => $q->orderByDesc('facilities_count')->orderByDesc('id'),
            'members' => $q->orderByDesc('members_count')->orderByDesc('id'),
            'stores' => $q->orderByDesc('stores_count')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        };
    }
}
