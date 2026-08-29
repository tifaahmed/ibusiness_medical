<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        if (!$user || !Hash::check($request->password, $user->password)) {
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
        $user = $request->user()->load('membership');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_url' => get_image_url($user, 'avatar'),
                    'membership' => $user->membership ? [
                        'id' => $user->membership->id,
                        'membership_number' => $user->membership->membership_number,
                        'slug' => $user->membership->slug,
                        'registration_date' => $user->membership->registration_date?->format('Y-m-d'),
                        'expiration_date' => $user->membership->expiration_date?->format('Y-m-d'),
                        'is_active' => $user->membership->is_active ?? false,
                        'job_title' => $user->membership->getTranslation('job_title', app()->getLocale()),
                        'company_name' => $user->membership->company?->getTranslation('name', app()->getLocale()),
                    ] : null,
                ],
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
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
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث البيانات بنجاح.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_url' => get_image_url($user, 'avatar'),
                ],
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

        if (!Hash::check($request->current_password, $user->password)) {
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
