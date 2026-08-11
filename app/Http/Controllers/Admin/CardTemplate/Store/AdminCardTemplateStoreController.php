<?php

namespace App\Http\Controllers\Admin\CardTemplate\Store;

use App\Http\Controllers\Admin\CardTemplate\Concerns\HandlesCardTemplateUploads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CardTemplate\CardTemplateRequest;
use App\Models\CardTemplate;
use App\Support\CardTemplateLayoutDefaults;
use Illuminate\Http\JsonResponse;

class AdminCardTemplateStoreController extends Controller
{
    use HandlesCardTemplateUploads;

    public function __invoke(CardTemplateRequest $request): JsonResponse
    {
        $data = $request->safe()->except($this->nonColumnInputs());
        $data = $this->storeArtwork($request, $data);

        // A template with no layout posted starts from the shipped defaults
        // rather than blank, so it renders something immediately.
        $data['layout'] = $data['layout'] ?? CardTemplateLayoutDefaults::layout();
        $data['sample_data'] = $this->mergeSampleImageUploads(
            $request,
            $data['sample_data'] ?? CardTemplateLayoutDefaults::sampleData(),
        );

        $cardTemplate = CardTemplate::create($data);

        return response()->json(['data' => $cardTemplate], 201);
    }
}
