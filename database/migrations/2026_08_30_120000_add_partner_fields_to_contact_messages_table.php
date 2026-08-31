<?php

use App\Enums\Contact\ContactSourceEnum;
use App\Enums\Contact\ContactStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the enquiries the Deilar storefront used to keep in its own database
 * over to this table, which is where they are worked from now.
 *
 * What the storefront had and this table did not: which form the enquiry came
 * through, the commercial register number a joining facility applies with, the
 * language and page the visitor submitted from, and the salesperson it is
 * assigned to.
 *
 * The status vocabulary changes with it — the inbox's read/replied/archived
 * becomes the pipeline sales actually work. Safe to rewrite in place because
 * the table was empty when this was written; the `new` case is unchanged, so
 * the public form at `POST /api/contact-messages` keeps working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('source')
                ->default(ContactSourceEnum::CONTACT_FORM->value)
                ->after('message');

            /* Only the join form collects one: a medical facility applying to
               the network is the only enquiry there is anything to verify. */
            $table->string('commercial_register')->nullable()->after('phone');

            /* 5 characters holds `ar` and `en` and anything regional. */
            $table->string('locale', 5)->nullable()->after('user_agent');
            $table->text('referrer')->nullable()->after('locale');

            /* nullOnDelete, matching memberships.sales_id and
               facilities.sales_id: removing a salesperson must never take the
               enquiries they were working with them. */
            $table->foreignId('sales_id')->nullable()->after('status')
                ->constrained('sales')->nullOnDelete();

            $table->index('source');
            $table->index('sales_id');
        });

        /* The old vocabulary mapped onto the new one. A no-op on an empty
           table, and the right answer on one that is not. */
        DB::table('contact_messages')->where('status', 'read')
            ->update(['status' => ContactStatusEnum::IN_PROGRESS->value]);
        DB::table('contact_messages')->where('status', 'replied')
            ->update(['status' => ContactStatusEnum::RESOLVED->value]);
        DB::table('contact_messages')->where('status', 'archived')
            ->update(['status' => ContactStatusEnum::CLOSED->value]);
    }

    public function down(): void
    {
        DB::table('contact_messages')->where('status', ContactStatusEnum::IN_PROGRESS->value)
            ->update(['status' => 'read']);
        DB::table('contact_messages')->where('status', ContactStatusEnum::RESOLVED->value)
            ->update(['status' => 'replied']);
        DB::table('contact_messages')->where('status', ContactStatusEnum::CLOSED->value)
            ->update(['status' => 'archived']);

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign(['sales_id']);
            $table->dropIndex(['sales_id']);
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'commercial_register', 'locale', 'referrer', 'sales_id']);
        });
    }
};
