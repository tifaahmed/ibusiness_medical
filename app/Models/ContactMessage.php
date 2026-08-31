<?php

namespace App\Models;

use App\Enums\Contact\ContactSourceEnum;
use App\Enums\Contact\ContactStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An enquiry from a public form — this site's own, or the Deilar storefront's.
 *
 * Storefront enquiries arrive at the key-gated `/api/v1/partner/contact-messages`
 * rather than being written directly: `ip_address`, `user_agent`, `locale` and
 * `referrer` describe the VISITOR and come in the request body, because
 * `$request->ip()` on that endpoint is the storefront's server.
 */
class ContactMessage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'commercial_register',
        'subject',
        'message',
        'source',
        'status',
        'sales_id',
        'admin_notes',
        'ip_address',
        'user_agent',
        'locale',
        'referrer',
        'read_at',
        'replied_at',
        'created_by',
    ];

    /**
     * The model's default attribute values.
     *
     * Mirrors the column defaults so a freshly made instance carries a status
     * and a source before it has been read back from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ContactStatusEnum::NEW->value,
        'source' => ContactSourceEnum::CONTACT_FORM->value,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The salesperson working the enquiry.
     */
    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class, 'sales_id');
    }

    /**
     * The enquiry's audit trail, oldest first.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ContactMessageLog::class)->oldest('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatusEnum::class,
            'source' => ContactSourceEnum::class,
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Every status as the admin filters render them.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return array_map(
            fn (array $option) => $option['label'],
            ContactStatusEnum::getOptions(),
        );
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', ContactStatusEnum::NEW);
    }

    /**
     * Never opened by anybody. Not the same as `new`: an enquiry can be read
     * and left on `new` because nobody has picked it up yet.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Stamp the first time an admin opened it.
     *
     * Deliberately does NOT move the status any more: where the status is a
     * pipeline, "somebody looked at it" is not a stage — an unassigned enquiry
     * that an admin glanced at is still new work.
     */
    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isNew(): bool
    {
        return $this->status === ContactStatusEnum::NEW;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isResolved(): bool
    {
        return $this->status === ContactStatusEnum::RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === ContactStatusEnum::CLOSED;
    }
}
