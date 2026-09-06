<?php

use App\Support\PhoneNumbers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `facility_branches.phone` becomes a list of typed entries.
 *
 * Before: ["0663400006", "01020709993"]
 * After:  [{"number":"0663400006","type":"landline"},{"number":"01020709993","type":"phone"}]
 *
 * The type of an existing number is inferred from its shape — 11 digits
 * starting 01 is a mobile, anything else a landline — because that is the only
 * thing the old rows say about them. It is a starting point an admin corrects
 * in the branch form, where "whatsapp" and "phone & whatsapp" are also offered;
 * neither could ever be guessed from a number alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->eachBranch(function (int $id, array $decoded) {
            $entries = PhoneNumbers::entries($decoded);

            DB::table('facility_branches')
                ->where('id', $id)
                ->update(['phone' => $entries === [] ? null : json_encode($entries, JSON_UNESCAPED_UNICODE)]);
        });
    }

    /**
     * Back to a flat list of numbers. The types are dropped — there is nowhere
     * in the old shape to keep them.
     */
    public function down(): void
    {
        $this->eachBranch(function (int $id, array $decoded) {
            $numbers = PhoneNumbers::numbers($decoded);

            DB::table('facility_branches')
                ->where('id', $id)
                ->update(['phone' => $numbers === [] ? null : json_encode($numbers, JSON_UNESCAPED_UNICODE)]);
        });
    }

    /**
     * Walk every branch that has a phone value, in chunks, handing the decoded
     * column to the callback. Uses the query builder rather than the model so
     * the model's own normalisation cannot mask what is really stored.
     */
    private function eachBranch(callable $callback): void
    {
        DB::table('facility_branches')
            ->select('id', 'phone')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunk(200, function ($branches) use ($callback) {
                foreach ($branches as $branch) {
                    $decoded = json_decode((string) $branch->phone, true);

                    if (! is_array($decoded) || $decoded === []) {
                        continue;
                    }

                    $callback((int) $branch->id, $decoded);
                }
            });
    }
};
