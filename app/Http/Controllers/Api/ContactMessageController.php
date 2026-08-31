<?php

namespace App\Http\Controllers\Api;

use App\Actions\Contact\RecordContactMessageAction;
use App\Enums\Contact\ContactSourceEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMessageReceived;

class ContactMessageController extends Controller
{
    /**
     * Store a newly created contact message.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            /*
             * Through the same action the partner endpoint uses, so this
             * site's own form and the storefront's write identical rows — with
             * an opening log entry, and the inbox told.
             *
             * `$request->ip()` IS the visitor here: unlike the partner
             * endpoint, this form is submitted by the browser itself.
             */
            $contactMessage = app(RecordContactMessageAction::class)->handle([
                'phone' => $request->phone,
                'message' => $request->message,
                'source' => ContactSourceEnum::CONTACT_FORM->value,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'locale' => app()->getLocale(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully! We will get back to you soon.',
                'data' => new ContactMessageResource($contactMessage)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending your message. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

