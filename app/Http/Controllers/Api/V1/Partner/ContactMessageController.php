<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Actions\Contact\RecordContactMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Partner\StoreContactMessageRequest;
use App\Http\Resources\ContactMessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Enquiries forwarded from a partner storefront's public forms (the Deilar
 * site: its contact page, its card popup and its "join the network" form).
 *
 * Key-gated rather than public, for the same two reasons the partner order
 * endpoint is: it WRITES, and the caller speaks for its visitor — the
 * visitor's own IP, user agent, language and referring page arrive in the body
 * because `$request->ip()` here is the storefront's server.
 *
 * The storefront queues its call and retries, so this answering 500 is not a
 * lost enquiry — but it is a duplicate risk, which is why the mail failing is
 * handled inside the action rather than being allowed to fail the write.
 */
class ContactMessageController extends Controller
{
    public function __construct(private readonly RecordContactMessageAction $recordContactMessage) {}

    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        try {
            $enquiry = $this->recordContactMessage->handle($request->enquiry());
        } catch (Throwable $exception) {
            Log::error('Partner contact enquiry failed.', [
                'source' => $request->input('source'),
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The enquiry could not be recorded.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'data' => new ContactMessageResource($enquiry),
        ], Response::HTTP_CREATED);
    }
}
