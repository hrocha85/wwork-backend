<?php

namespace App\Models;

use App\Enums\StaffPermissionCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StaffPermission extends Model
{
    protected $fillable = [
        'code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => StaffPermissionCode::class,
        ];
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'staff_profile_permission');
    }
}
