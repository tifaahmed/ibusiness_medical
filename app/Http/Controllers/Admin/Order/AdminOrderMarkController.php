<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller as BaseController;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sets the admin's own mark on an order row: `done` (finished, real money), `test` (a test order) or none. It changes nothing else. */
class AdminOrderMarkController extends BaseController
{
    public function __invoke(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['mark' => ['nullable', 'in:done,test']]);

        $model = Order::query()->where('order_code', $order)->firstOrFail();
        $model->forceFill(['admin_mark' => $data['mark'] ?? null])->save();

        return back();
    }
}
