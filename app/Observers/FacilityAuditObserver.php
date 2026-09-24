<?php

namespace App\Observers;

use App\Support\FacilityAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * Registered on Facility, FacilityBranch and FacilityManager. Silent unless a
 * bulk tool has named itself through FacilityAudit::as() — see that class.
 */
class FacilityAuditObserver
{
    public function created(Model $model): void
    {
        if (FacilityAudit::source() !== null) {
            FacilityAudit::record($model, null);
        }
    }

    public function updated(Model $model): void
    {
        if (FacilityAudit::source() === null) {
            return;
        }

        // Inside `updated` the raw original still holds the pre-save values.
        FacilityAudit::record($model, FacilityAudit::snapshot($model, $model->getRawOriginal()));
    }
}
