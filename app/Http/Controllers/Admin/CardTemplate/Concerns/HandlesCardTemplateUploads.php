<?php

namespace App\Http\Controllers\Admin\CardTemplate\Concerns;

use App\Http\Requests\Admin\CardTemplate\CardTemplateRequest;

trait HandlesCardTemplateUploads
{
    /**
     * Validated inputs that are uploaded files rather than table columns, or
     * that belong inside the sample_data JSON — leaving them in the payload
     * would have Eloquent try to write them as columns.
     *
     * @return array<string>
     */
    protected function nonColumnInputs(): array
    {
        return ['clear_sample_card', ...CardTemplateRequest::sampleImageUploadKeys()];
    }

    /**
     * Move any uploaded artwork onto the public disk and swap the payload's
     * file objects for the URL paths that get stored in the columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function storeArtwork(CardTemplateRequest $request, array $data): array
    {
        foreach (['card_empty', 'sample_card'] as $column) {
            if ($request->hasFile($column)) {
                $data[$column] = $this->put($request->file($column));
            } else {
                // No new upload — never overwrite the existing path with null.
                unset($data[$column]);
            }
        }

        // Removing the sample card is an explicit action, not the absence of an
        // upload, so it needs its own flag.
        if ($request->boolean('clear_sample_card') && ! $request->hasFile('sample_card')) {
            $data['sample_card'] = null;
        }

        return $data;
    }

    /**
     * Upload any provided sample images and merge their resolved URLs into the
     * sample_data payload.
     *
     * @param  array<string, mixed>  $sampleData
     * @return array<string, mixed>
     */
    protected function mergeSampleImageUploads(CardTemplateRequest $request, array $sampleData): array
    {
        foreach (CardTemplateRequest::UPLOADABLE_SAMPLE_IMAGE_FIELDS as $field) {
            if ($request->hasFile("sample_{$field}")) {
                // Unlike the columns, sample_data has no `*_url` accessor to add
                // the leading slash, and the renderer loads these paths straight
                // from the JSON — so store them root-relative, the way the
                // shipped defaults ('/images/logo/dielar.png') are written.
                $sampleData[$field] = '/'.$this->put($request->file("sample_{$field}"));
            }
        }

        return $sampleData;
    }

    /**
     * Store on the public disk and return a root-relative path.
     *
     * Deliberately NOT Storage::url(), which yields an absolute URL
     * (http://host/storage/…) that the model's `*_url` accessor would then
     * prefix with another slash. Columns hold host-less paths — matching the
     * seeded 'images/cards/…' values — so the same row works whatever host or
     * scheme the app is served under.
     */
    private function put(\Illuminate\Http\UploadedFile $file): string
    {
        return 'storage/'.$file->store('card-templates', 'public');
    }
}
