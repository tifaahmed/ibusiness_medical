<?php

namespace App\Http\Controllers\Admin\CardTemplate\Update;

use App\Http\Controllers\Admin\CardTemplate\Concerns\HandlesCardTemplateUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CardTemplate\CardTemplateRequest;
use App\Models\CardTemplate;
use Illuminate\Http\JsonResponse;

class AdminCardTemplateUpdateController extends Controller
{
    use HandlesCardTemplateUploads;

    public function __invoke(CardTemplateRequest $request, CardTemplate $cardTemplate): JsonResponse
    {
        $data = $request->safe()->except($this->nonColumnInputs());
        $data = $this->storeArtwork($request, $data);

        // Merge rather than replace: the form may post only the fields it shows
        // for the chosen status, and dropping the rest would lose their values.
        $data['sample_data'] = $this->mergeSampleImageUploads(
            $request,
            array_merge($cardTemplate->sample_data ?? [], $data['sample_data'] ?? []),
        );

        $cardTemplate->update($data);

        return response()->json(['data' => $cardTemplate->fresh()]);
    }
}
