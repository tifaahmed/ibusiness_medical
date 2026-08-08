<?php

namespace App\Http\Controllers\Api;

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
            $contactMessage = ContactMessage::create([
                'phone' => $request->phone,
                'message' => $request->message,
                'status' => ContactMessage::STATUS_NEW,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Optional: Send email notification to admin
            // Mail::to(config('mail.admin_email'))->send(new ContactMessageReceived($contactMessage));

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

