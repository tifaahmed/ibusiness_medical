<?php

namespace App\Http\Controllers\Admin\Faq\List;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Faq\List\AdminFaqListCollection;
use App\Models\Faq;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFaqListController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FAQS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FAQS; }

    public function __invoke(Request $request): Response
    {
        $filters = $this->getFilters($request);

        $faqs = Faq::query()
            ->with('creator:id,name,email')
            ->tap(fn($q) => $this->applyCreatorScope($q))
            ->when(!empty($filters['search']), function ($q) use ($filters) {
                $q->where(function ($query) use ($filters) {
                    $query->where('question->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%')
                          ->orWhere('answer->' . app()->getLocale(), 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', function ($q) use ($filters) {
                $q->where('is_active', (bool) $filters['is_active']);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($request->input('per_page', 15))->withQueryString();

        return Inertia::render('Admin/Faq/List', [
            'faqs' => (new AdminFaqListCollection($faqs))->toArray($request),
            'filters' => $filters,
        ]);
    }

    protected function getFilters(Request $request): array
    {
        return [
            'search' => $request->input('search', ''),
            'is_active' => $request->input('is_active', ''),
        ];
    }
}
