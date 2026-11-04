<?php

use App\Models\Setting;
use App\Support\QuickAdd;
use App\Support\SiteSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Quick add": a member a salesperson registers on the phone, marked for good. ADD-ONLY:
 *  - memberships.quick_added_at / quick_added_by / quick_device / quick_wants_card (the mark never goes away)
 *  - sms_messages: every message this feature writes, with its length and whether it was sent or only queued
 *  - settings: the sending switch (OFF), and the trial days per kind (7 / 30)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            if (! Schema::hasColumn('memberships', 'quick_added_at')) {
                $table->timestamp('quick_added_at')->nullable()->after('member_kind');
                $table->foreignId('quick_added_by')->nullable()->after('quick_added_at')->constrained('users')->nullOnDelete();
                $table->string('quick_device', 10)->nullable()->after('quick_added_by'); // android | iphone
                $table->boolean('quick_wants_card')->default(false)->after('quick_device');
                $table->index('quick_added_at');
            }
        });

        if (! Schema::hasTable('sms_messages')) {
            Schema::create('sms_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('membership_id')->nullable()->constrained('memberships')->nullOnDelete();
                $table->string('phone', 30);
                $table->string('purpose', 40); // quick_add
                $table->text('body');
                $table->unsignedSmallInteger('characters');
                $table->unsignedSmallInteger('segments');
                $table->string('status', 12)->default('queued'); // queued | sent | failed
                $table->string('reference')->nullable();
                $table->text('error')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }

        foreach (QuickAdd::seedRows() as $row) {
            if (! Setting::query()->where('slug', $row['slug'])->exists()) {
                SiteSettings::put($row['slug'], $row['value'], $row['value_type'], $row['name']);
            }
        }

        SiteSettings::forget();
    }

    public function down(): void
    {
        // Left in place: the mark and the message log are history.
    }
};
