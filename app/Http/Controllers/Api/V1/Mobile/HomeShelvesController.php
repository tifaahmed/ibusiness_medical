<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Mobile\Concerns\RespondsCompactly;
use App\Support\HomeShelves;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeShelvesController extends Controller
{
    use RespondsCompactly;

    /** The six home strips from the saved copy (refreshed daily by `home:shelves-refresh`): one cheap read, no listing queries. */
    public function __invoke(Request $request): JsonResponse
    {
        return $this->respond($request, HomeShelves::get(app()->getLocale()), 600);
    }
}
