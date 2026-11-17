<?php

namespace App\Http\Controllers\Admin\MembershipCard\Check;

use App\Http\Controllers\Controller as BaseController;
use App\Support\BatchNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a new batch would take, before it is made: which numbers in the run are already held (a membership, even a
 * deleted one, or another batch), the run with those stepped over, or a random set of free numbers. Writes nothing.
 */
class AdminMembershipCardCheckController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:32'],
            'start_number' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'skip_taken' => ['nullable', 'boolean'],
            'random' => ['nullable', 'boolean'],
        ]);
        $prefix = (string) ($data['prefix'] ?? '');
        $qty = (int) $data['quantity'];

        if (! empty($data['random'])) {
            return response()->json(['numbers' => BatchNumbers::randomNumbers($prefix, $qty), 'taken' => [], 'skipped' => []]);
        }

        abort_if(empty($data['start_number']), 422, 'start_number is required.');
        $plan = BatchNumbers::plan($prefix, (int) $data['start_number'], $qty, ! empty($data['skip_taken']));

        // `taken` is only ever the clash inside the run; capped so a huge list never floods the page.
        return response()->json([
            'numbers' => $plan['numbers'],
            'taken' => array_slice($plan['taken'], 0, 200),
            'taken_count' => count($plan['taken']),
            'skipped' => $plan['skipped'],
        ]);
    }
}
