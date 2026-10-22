<?php

namespace App\Http\Controllers\Admin\Visitor;

use App\Enums\Visitor\VisitSourceEnum;
use App\Http\Controllers\Controller;
use App\Models\Governorate;
use App\Models\PageVisit;
use App\Models\Product;
use App\Models\Visitor;
use App\Support\VisitorPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminVisitorController extends Controller
{
    /** All visitors, newest activity first. */
    public function index(Request $request): Response
    {
        $filters = $request->only(['source', 'identified', 'governorate_id', 'search', 'bots']);

        $query = Visitor::query()
            ->with(['user:id,name,phone,email', 'governorate', 'city'])
            ->when(! $request->boolean('bots'), fn (Builder $q) => $q->fromPeople())
            ->when($request->filled('source') && $request->input('source') !== 'all', fn (Builder $q) => $q->where('source', $request->input('source')))
            ->when($request->input('identified') === 'yes', fn (Builder $q) => $q->where(fn ($w) => $w->whereNotNull('user_id')->orWhereNotNull('phone')->orWhereNotNull('email')))
            ->when($request->input('identified') === 'member', fn (Builder $q) => $q->whereNotNull('user_id'))
            ->when($request->input('identified') === 'no', fn (Builder $q) => $q->whereNull('user_id')->whereNull('phone')->whereNull('email'))
            ->when($request->filled('governorate_id'), fn (Builder $q) => $q->where('governorate_id', $request->input('governorate_id')))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = '%'.$request->string('search')->trim().'%';
                $q->where(fn ($w) => $w->where('phone', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhere('name', 'like', $term)->orWhere('ip_address', 'like', $term)->orWhere('visitor_key', 'like', $term));
            });

        $visitors = $query->orderByDesc('last_seen_at')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Visitors/Index', [
            'visitors' => $visitors->through(fn (Visitor $v) => VisitorPresenter::visitor($v)),
            'filters' => $filters,
            'sources' => array_values(VisitSourceEnum::getOptions()),
            'governorates' => Governorate::orderBy('id')->get(['id', 'name'])->map(fn ($g) => ['id' => $g->id, 'name' => $g->getTranslation('name', app()->getLocale())]),
            'stats' => [
                'total' => Visitor::fromPeople()->count(),
                'identified' => Visitor::fromPeople()->where(fn ($w) => $w->whereNotNull('user_id')->orWhereNotNull('phone')->orWhereNotNull('email'))->count(),
                'members' => Visitor::fromPeople()->whereNotNull('user_id')->count(),
                'by_source' => Visitor::fromPeople()->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source'),
            ],
        ]);
    }

    public function show(Visitor $visitor): Response
    {
        $visitor->load(['user:id,name,phone,email', 'governorate', 'city']);

        $visits = $visitor->visits()
            ->with(['product', 'facility', 'governorate', 'city'])
            ->orderByDesc('visited_at')
            ->paginate(30);

        return Inertia::render('Admin/Visitors/Show', [
            'visitor' => VisitorPresenter::visitor($visitor),
            'visits' => $visits->through(fn (PageVisit $p) => VisitorPresenter::visit($p)),
        ]);
    }

    /** JSON for the product page's "Visitors" tab. */
    public function product(Request $request, string $product): JsonResponse
    {
        $product = Product::where('slug', $product)->firstOrFail();

        $base = PageVisit::query()->where('product_id', $product->id)
            ->when(! $request->boolean('bots'), fn (Builder $q) => $q->fromPeople())
            ->when($request->filled('source') && $request->input('source') !== 'all', fn (Builder $q) => $q->where('source', $request->input('source')))
            ->when($request->input('identified') === 'yes', fn (Builder $q) => $q->whereHas('visitor', fn ($v) => $v->where(fn ($w) => $w->whereNotNull('user_id')->orWhereNotNull('phone')->orWhereNotNull('email'))))
            ->when($request->input('identified') === 'member', fn (Builder $q) => $q->whereNotNull('user_id'));

        $rows = (clone $base)->with(['visitor.user:id,name,phone,email', 'visitor.governorate', 'visitor.city', 'governorate', 'city'])
            ->orderByDesc('visited_at')->paginate(20)->withQueryString();

        return response()->json([
            'stats' => [
                'views' => (clone $base)->count(),
                'unique_visitors' => (clone $base)->distinct('visitor_id')->count('visitor_id'),
                'members' => (clone $base)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'by_source' => (clone $base)->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source'),
            ],
            'sources' => array_values(VisitSourceEnum::getOptions()),
            'visits' => [
                'data' => $rows->getCollection()->map(fn (PageVisit $p) => VisitorPresenter::visit($p))->values(),
                'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
            ],
        ]);
    }
}
