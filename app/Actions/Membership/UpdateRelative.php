<?php

namespace App\Actions\Membership;

use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff editing a relative: the person's fields live on its user, the
 * relation and National ID on its membership. Same rules as creating one —
 * a repeated phone / e-mail / National ID is refused and the relative keeps at
 * least one of phone, date of birth, National ID.
 */
class UpdateRelative
{
    public function __construct(private CreateRelative $create) {}

    /**
     * @param  array<string, mixed>  $data  name, relationship, phone, email, date_of_birth, national_id, is_active (any subset; a key that is absent is left alone)
     */
    public function handle(Membership $relative, array $data): Membership
    {
        $user = $relative->user;
        $primary = $relative->parent;

        $has = fn (string $key) => array_key_exists($key, $data);
        $norm = fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v;

        $relationship = $has('relationship') ? (string) $data['relationship'] : (string) $relative->relationship;
        $phone = $has('phone') ? $norm($data['phone']) : $user->phone;
        $email = $has('email') ? $norm($data['email']) : $user->email;
        $nationalId = $has('national_id') ? $norm($data['national_id']) : $relative->national_id;
        $dob = $has('date_of_birth') ? $norm($data['date_of_birth']) : $user->date_of_birth;
        $name = $has('name') ? CreateRelative::resolveName((string) $data['name'], $relationship, (string) $primary?->user?->name) : $user->name;

        if (blank($phone) && blank($nationalId) && blank($dob)) {
            throw ValidationException::withMessages(['phone' => ['Enter at least a phone, a date of birth or a National ID.']]);
        }

        $this->create->assertFree(
            $phone !== $user->phone ? $phone : null,
            $email !== $user->email ? $email : null,
            $nationalId !== $relative->national_id ? $nationalId : null,
            $user->id,
            $relative->id,
        );

        return DB::transaction(function () use ($relative, $user, $name, $relationship, $phone, $email, $nationalId, $dob, $has, $data) {
            $user->forceFill([
                'name' => $name,
                'phone' => $phone,
                // A different phone has not been proven by any code yet.
                'phone_verified_at' => $phone === $user->phone ? $user->phone_verified_at : null,
                'email' => $email,
                'date_of_birth' => $dob,
            ])->save();

            $attributes = ['relationship' => $relationship, 'national_id' => $nationalId];

            if ($has('is_active')) {
                $attributes['is_active'] = (bool) $data['is_active'];
            }

            $relative->forceFill($attributes)->save();

            return $relative->refresh();
        });
    }
}
