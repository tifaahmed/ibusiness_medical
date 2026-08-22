<?php

namespace App\Http\Controllers\Admin\Faq\Show;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Faq\Show\AdminFaqShowResource;
use App\Models\Faq;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFaqShowController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string
    {
        return UserPermissionEnum::MANAGE_FAQS;
    }

    protected function ownPermission(): string
    {
        return UserPermissionEnum::MANAGE_OWN_FAQS;
    }

    public function __invoke(Request $request, Faq $faq): Response
    {
        $faq->load('creator:id,name,email');
        $this->assertOwns($faq);

        return Inertia::render('Admin/Faq/Show', [
            'faq' => (new AdminFaqShowResource($faq))->toArray($request),
        ]);
    }
}
