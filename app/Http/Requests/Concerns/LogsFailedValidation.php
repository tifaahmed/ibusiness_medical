<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

/**
 * A rejected admin form otherwise leaves no trace on the server: the errors go
 * back to the browser and nowhere else. Logging which fields failed (never the
 * values — those can be large or personal) is what makes "I pressed save and
 * nothing happened" answerable from the log.
 */
trait LogsFailedValidation
{
    protected function failedValidation(Validator $validator): void
    {
        Log::warning('Form validation failed', [
            'request' => static::class,
            'route' => $this->route()?->getName(),
            'url' => $this->fullUrl(),
            'user_id' => $this->user()?->id,
            'errors' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}
