<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Models\CardTemplate;
use App\Support\CardTemplateLayoutDefaults;

/**
 * Serves the card designs an admin screen draws on, keyed by status.
 *
 * Every card-drawing screen picks between the same two designs the same way:
 * a card that carries a partner logo uses the `with_partner` template, one
 * that does not uses `no_partner`. The choice happens in the browser (the
 * partner select is client-side), so both are handed over and the page picks.
 */
trait ProvidesCardTemplates
{
    /**
     * @return array<string, CardTemplate|null>
     */
    protected function cardTemplatesByStatus(): array
    {
        $templates = CardTemplate::orderBy('id')->get()->keyBy(
            fn (CardTemplate $t) => $t->status->value,
        );

        $payload = [];

        foreach (CardTemplateStatusEnum::cases() as $status) {
            $template = $templates->get($status->value);

            // A row saved before a field existed — or created straight through
            // the model — can be missing its layout. Hand the page the shipped
            // defaults rather than nothing to position.
            if ($template) {
                $template->layout = $template->layout ?: CardTemplateLayoutDefaults::layout();
                $template->sample_data = $template->sample_data ?: CardTemplateLayoutDefaults::sampleData();
            }

            $payload[$status->value] = $template;
        }

        return $payload;
    }
}
