<?php

namespace App\Models;

use App\Enums\StaffProfileCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StaffProfile extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => StaffProfileCode::class,
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(StaffPermission::class, 'staff_profile_permission');
    }
}
