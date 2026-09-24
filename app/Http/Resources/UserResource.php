<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O recorte que o PWA recebe de quem está logado.
 *
 * O GET /me é o que decide a tela, então o papel precisa vir. O que não é da
 * pessoa — equipe, agência, degrau da mensalidade — é de fatias seguintes.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sync_uuid' => $this->sync_uuid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'locale' => $this->locale,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
