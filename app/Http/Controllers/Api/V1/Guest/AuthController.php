<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\User;
use App\Services\Otp\RegistrationProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->login)
            ->orWhere('phone', $request->login)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            Log::warning('API login failed', ['login' => $request->login]);
            throw ValidationException::withMessages([
                'login' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_url' => get_image_url($user, 'avatar'),
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
         * `memberships`, not the `membership` relation: that one is scoped to
         * ACTIVE cards, and a member who registered themselves on the
         * storefront holds an inactive pending one until staff issue a real
         * card. Reading through the active-only relation would show them an
         * account with no membership at all and no way to see their progress.
         */
        $membership = $user->memberships()->with(['company', 'governorate', 'city'])->latest('id')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'gender' => $user->gender,
                    'avatar_url' => get_image_url($user, 'avatar'),
                    'membership' => $membership ? [
                        'id' => $membership->id,
                        'membership_number' => $membership->membership_number,
                        'slug' => $membership->slug,
                        'registration_date' => $membership->registration_date?->format('Y-m-d'),
                        'expiration_date' => $membership->expiration_date?->format('Y-m-d'),
                        'is_active' => $membership->is_active ?? false,
                        'job_title' => $membership->getTranslation('job_title', app()->getLocale()),
                        'company_name' => $membership->company?->getTranslation('name', app()->getLocale()),
                        'national_id' => $membership->national_id,
                        'governorate_id' => $membership->governorate_id,
                        'governorate_name' => $membership->governorate?->getTranslation('name', app()->getLocale()),
                        'city_id' => $membership->city_id,
                        'city_name' => $membership->city?->getTranslation('name', app()->getLocale()),
                    ] : null,
                ],
                /*
                 * How far through registration this member is, as two bars. The
                 * storefront draws a nudge off it and stops once there is
                 * nothing left to ask — see `RegistrationProgress`, which is
                 * here rather than over there because this application owns the
                 * fields being counted.
                 */
                'registration' => app(RegistrationProgress::class)->for($user),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:users,email,'.$user->id,
            /*
             * The phone is not just a contact detail for a member: it is what
             * they sign in with. `unique` is therefore load-bearing here rather
             * than tidy — two rows carrying the same number would make the
             * phone login ambiguous about whose account a code opens.
             */
            'phone' => 'sometimes|string|max:20|unique:users,phone,'.$user->id,
            /*
             * The member's position, which lives on the MEMBERSHIP and is
             * translatable — it is printed on the card, in the language the
             * card is printed in. Accepted for the locale this request is being
             * read in, so an Arabic edit never overwrites the English line.
             */
            'job_title' => 'sometimes|nullable|string|max:255',
            /*
             * The registration form's fields. Every one of them is optional by
             * design: somebody proves they hold a phone and is let in, and the
             * details are a nudge afterwards rather than a gate. `nullable` is
             * what makes clearing a box mean "I would rather not say" instead
             * of a validation error.
             */
            'gender' => 'sometimes|nullable|string|in:male,female',
            'national_id' => 'sometimes|nullable|string|max:14',
            'governorate_id' => 'sometimes|nullable|integer|exists:governorates,id',
            /*
             * The city is checked against the CHOSEN governorate, not merely
             * against the table: a city from another governorate is a pair no
             * address can be delivered to, and it is the sort of thing a form
             * whose two selects got out of step will happily post.
             */
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        if ($request->has('gender')) {
            $user->gender = $request->input('gender');
        }
        $user->save();

        // The pending membership of a self-registration counts here too.
        $membership = $user->memberships()->latest('id')->first();

        if ($membership) {
            if ($request->has('job_title')) {
                $membership->setTranslation('job_title', app()->getLocale(), (string) $request->input('job_title'));
            }

            foreach (['national_id', 'governorate_id', 'city_id'] as $field) {
                if ($request->has($field)) {
                    $membership->{$field} = $request->input($field);
                }
            }

            /*
             * A city that does not belong to the chosen governorate is dropped
             * rather than saved: the two are asked for as one answer, and half
             * of it is worse than none. Checked here rather than in the rules
             * above because the governorate it has to agree with may be the one
             * already stored, not one in this request.
             */
            if ($membership->city_id !== null && $membership->governorate_id !== null
                && ! City::query()
                    ->whereKey($membership->city_id)
                    ->where('governorate_id', $membership->governorate_id)
                    ->exists()) {
                $membership->city_id = null;
            }

            $membership->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث البيانات بنجاح.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'gender' => $user->gender,
                    'avatar_url' => get_image_url($user, 'avatar'),
                    'job_title' => $membership?->getTranslation('job_title', app()->getLocale()),
                ],
                'registration' => app(RegistrationProgress::class)->for($user->refresh()),
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح.',
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $request->user();

        // The collection is not registered as singleFile and get_image_url reads
        // getMedia('avatar')->first(), so without clearing it the old avatar
        // would keep being served after an upload.
        $user->clearMediaCollection('avatar');
        $user->addMediaFromRequest('avatar')
            ->toMediaCollection('avatar');

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصورة الشخصية بنجاح.',
            'data' => [
                'avatar_url' => get_image_url($user, 'avatar'),
            ],
        ]);
    }
}
