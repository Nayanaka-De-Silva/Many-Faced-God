<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NpcTraitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
        ];
    }
}
