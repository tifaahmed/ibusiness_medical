<?php

namespace App\Http\Resources\Admin\PartnerOfferRequest\List;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminPartnerOfferRequestListCollection extends ResourceCollection
{
    public $collects = AdminPartnerOfferRequestListResource::class;

    protected $pagination;

    public function __construct($resource)
    {
        parent::__construct($resource);

        if ($resource instanceof LengthAwarePaginator) {
            $this->pagination = $resource;
        }
    }

    public function toArray(Request $request): array
    {
        $data = [
            'data' => $this->collection->map(function ($resource) use ($request) {
                return $resource->resolve($request);
            })->toArray(),
        ];

        if ($this->pagination instanceof LengthAwarePaginator) {
            $data['meta'] = [
                'current_page' => $this->pagination->currentPage(),
                'last_page' => $this->pagination->lastPage(),
                'per_page' => $this->pagination->perPage(),
                'total' => $this->pagination->total(),
                'from' => $this->pagination->firstItem(),
                'to' => $this->pagination->lastItem(),
                'links' => $this->pagination->linkCollection()->toArray(),
            ];
        }

        return $data;
    }
}
