<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'admin_notes' => $this->when($request->user()?->hasRole('admin'), $this->admin_notes),
            'ip_address' => $this->when($request->user()?->hasRole('admin'), $this->ip_address),
            'user_agent' => $this->when($request->user()?->hasRole('admin'), $this->user_agent),
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'replied_at' => $this->replied_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'is_new' => $this->isNew(),
            'is_read' => $this->isRead(),
            'is_replied' => $this->isReplied(),
            'is_archived' => $this->isArchived(),
        ];
    }

    /**
     * Get the status label
     *
     * @return string
     */
    private function getStatusLabel(): string
    {
        $statuses = \App\Models\ContactMessage::getStatuses();
        return $statuses[$this->status] ?? $this->status;
    }
}

