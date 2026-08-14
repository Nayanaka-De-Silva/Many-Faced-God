<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NpcActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'               => $this->name,
            'description'        => $this->description,
            // actionType key is camelCase, but the VALUE keeps its snake_case DB enum (e.g. "attack_action")
            'actionType'         => $this->action_type,
            'legendaryCost'      => $this->legendary_cost,
            // formattedAttackLine and formattedHitLine are null when the action is not an attack action
            'formattedAttackLine' => $this->isAttackAction() ? $this->formatted_attack_line : null,
            'formattedHitLine'   => $this->isAttackAction() ? $this->formatted_hit_line : null,
        ];
    }
}
