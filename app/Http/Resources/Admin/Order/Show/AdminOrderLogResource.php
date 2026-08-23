<?php

namespace App\Http\Resources\Admin\Order\Show;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the order's audit trail, ready to render as a timeline entry.
 */
class AdminOrderLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            /*
             * The admin's name is copied out rather than nested: a log whose
             * admin row was deleted still has to say what happened, and the
             * timeline only ever shows the name.
             */
            'admin' => $this->admin === null ? null : [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
                'email' => $this->admin->email,
            ],
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'changed_fields' => $this->changed_fields,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
