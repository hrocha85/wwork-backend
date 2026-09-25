<?php

namespace Database\Seeders;

use App\Enums\Locale;
use App\Enums\StaffPermissionCode;
use App\Enums\StaffProfileCode;
use App\Models\StaffPermission;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $password = $this->password();

        foreach (StaffPermissionCode::cases() as $permission) {
            StaffPermission::query()->updateOrCreate(
                ['code' => $permission->value],
            );
        }

        $matrix = [
            StaffProfileCode::Founder->value => [
                'name' => 'Fundador',
                'permissions' => StaffPermissionCode::cases(),
            ],
            StaffProfileCode::Support->value => [
                'name' => 'Atendimento',
                'permissions' => [
                    StaffPermissionCode::ViewAdminPanel,
                    StaffPermissionCode::ViewMetrics,
                    StaffPermissionCode::ManageAgencies,
                    StaffPermissionCode::ResetPassword,
                ],
            ],
            StaffProfileCode::Finance->value => [
                'name' => 'Financeiro',
                'permissions' => [
                    StaffPermissionCode::ViewAdminPanel,
                    StaffPermissionCode::ViewMetrics,
                    StaffPermissionCode::ViewRevenue,
                    StaffPermissionCode::ManageAgencies,
                    StaffPermissionCode::ManagePlans,
                ],
            ],
        ];

        $profiles = [];

        foreach ($matrix as $code => $row) {
            $profile = StaffProfile::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $row['name']],
            );

            $ids = StaffPermission::query()
                ->whereIn('code', array_map(
                    fn (StaffPermissionCode $permission) => $permission->value,
                    $row['permissions'],
                ))
                ->pluck('id');

            $profile->permissions()->sync($ids);
            $profiles[$code] = $profile;
        }

        $accounts = [
            ['email' => 'founder@wwork.app', 'name' => 'Henrique', 'profile' => StaffProfileCode::Founder->value],
            ['email' => 'support@wwork.app', 'name' => 'WWork Support', 'profile' => StaffProfileCode::Support->value],
            ['email' => 'finance@wwork.app', 'name' => 'WWork Finance', 'profile' => StaffProfileCode::Finance->value],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            if (! $user->exists) {
                $user->password = $password;
                $user->must_change_password = true;
                $user->locale = Locale::En;
            }

            $user->name = $account['name'];
            $user->staff_profile_id = $profiles[$account['profile']]->id;
            $user->save();
        }

        $this->command?->info('Staff: founder@wwork.app, support@wwork.app, finance@wwork.app');
        $this->command?->line('ADMIN_SEED_PASSWORD='.$password);
        $this->command?->warn('Troca no primeiro acesso ao /admin.');
    }

    private function password(): string
    {
        $password = config('wwork.admin_seed_password');

        if (! is_string($password) || strlen($password) < 8) {
            throw new RuntimeException('ADMIN_SEED_PASSWORD must be at least 8 characters.');
        }

        return $password;
    }
}
