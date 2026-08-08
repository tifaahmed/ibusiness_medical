<?php

namespace App\Http\Resources\Admin\PartnerOfferRequest\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPartnerOfferRequestListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_offer_id' => $this->partner_offer_id,
            'phone_number' => $this->phone_number,
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'is_unread' => $this->isUnread(),
            'offer' => $this->whenLoaded('partnerOffer', fn() => [
                'id' => $this->partnerOffer->id,
                'title' => $this->partnerOffer->title,
                'partner' => $this->partnerOffer->relationLoaded('partner') ? [
                    'id' => $this->partnerOffer->partner->id,
                    'title' => $this->partnerOffer->partner->title,
                ] : null,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
