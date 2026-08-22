<?php

namespace App\Http\Controllers\Admin\NewsTicker\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\NewsTicker\Show\AdminNewsTickerShowResource;
use App\Models\NewsTicker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminNewsTickerShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_NEWS_TICKERS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_NEWS_TICKERS;
    }

    public function __invoke(Request $request, NewsTicker $newsTicker): Response
    {
        $newsTicker->load('creator:id,name,email');
        $this->assertOwns($newsTicker);

        return Inertia::render('Admin/NewsTicker/Show', [
            'newsTicker' => (new AdminNewsTickerShowResource($newsTicker))->toArray($request),
        ]);
    }
}
