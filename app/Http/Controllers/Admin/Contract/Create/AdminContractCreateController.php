<?php

namespace App\Http\Controllers\Admin\Contract\Create;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminContractCreateController extends BaseController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Admin/Contract/Form/ContractFormView');
    }
}
