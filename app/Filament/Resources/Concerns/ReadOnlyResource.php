<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\StaffPermissionCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ReadOnlyResource
{
    public static function canViewAny(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ViewMetrics);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
