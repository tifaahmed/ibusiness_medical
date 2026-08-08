<?php

namespace App\Http\Controllers\Admin\Faq\Edit;

use App\Enums\User\UserPermissionEnum;
use App\Http\Controllers\Concerns\CreatorScoped;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Resources\Admin\Faq\Edit\AdminFaqEditResource;
use App\Models\Faq;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminFaqEditController extends BaseController
{
    use CreatorScoped;

    protected function fullPermission(): string { return UserPermissionEnum::MANAGE_FAQS; }
    protected function ownPermission(): string { return UserPermissionEnum::MANAGE_OWN_FAQS; }

    public function __invoke(Request $request, Faq $faq): Response
    {
        $this->assertOwns($faq);

        return Inertia::render('Admin/Faq/Form/FaqFormView', [
            'faq' => (new AdminFaqEditResource($faq))->toArray($request),
        ]);
    }
}
