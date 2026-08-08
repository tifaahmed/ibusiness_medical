<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    /**
     * Display a listing of contact messages.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = ContactMessage::query();

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $messages = $query->paginate(15)->withQueryString();

        // Get stats
        $stats = [
            'total' => ContactMessage::count(),
            'new' => ContactMessage::status(ContactMessage::STATUS_NEW)->count(),
            'read' => ContactMessage::status(ContactMessage::STATUS_READ)->count(),
            'replied' => ContactMessage::status(ContactMessage::STATUS_REPLIED)->count(),
            'archived' => ContactMessage::status(ContactMessage::STATUS_ARCHIVED)->count(),
        ];

        return Inertia::render('Admin/ContactMessages/Index', [
            'messages' => ContactMessageResource::collection($messages),
            'stats' => $stats,
            'filters' => [
                'status' => $request->status ?? 'all',
                'search' => $request->search ?? '',
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Display the specified contact message.
     *
     * @param ContactMessage $contactMessage
     * @return Response
     */
    public function show(ContactMessage $contactMessage): Response
    {
        // Mark as read
        $contactMessage->markAsRead();

        return Inertia::render('Admin/ContactMessages/Show', [
            'message' => new ContactMessageResource($contactMessage),
        ]);
    }

    /**
     * Update the specified contact message.
     *
     * @param Request $request
     * @param ContactMessage $contactMessage
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ContactMessage $contactMessage)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:new,read,replied,archived',
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $contactMessage->update($validated);

        if ($request->status === ContactMessage::STATUS_REPLIED && !$contactMessage->replied_at) {
            $contactMessage->markAsReplied();
        }

        return redirect()->back()->with('success', 'Contact message updated successfully.');
    }

    /**
     * Remove the specified contact message.
     *
     * @param ContactMessage $contactMessage
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ContactMessage $contactMessage)
    {
        $contactMessage->delete();

        return redirect()->route('admin.contact-messages.index')
            ->with('success', 'Contact message deleted successfully.');
    }

    /**
     * Bulk update contact messages status.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:contact_messages,id',
            'action' => 'required|in:mark_read,mark_replied,archive,delete',
        ]);

        $messages = ContactMessage::whereIn('id', $validated['ids']);

        switch ($validated['action']) {
            case 'mark_read':
                $messages->each(fn($msg) => $msg->markAsRead());
                $message = 'Messages marked as read successfully.';
                break;
            case 'mark_replied':
                $messages->each(fn($msg) => $msg->markAsReplied());
                $message = 'Messages marked as replied successfully.';
                break;
            case 'archive':
                $messages->each(fn($msg) => $msg->archive());
                $message = 'Messages archived successfully.';
                break;
            case 'delete':
                $messages->delete();
                $message = 'Messages deleted successfully.';
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }
}

