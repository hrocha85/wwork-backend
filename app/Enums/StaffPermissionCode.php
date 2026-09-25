<?php

namespace App\Enums;

enum StaffPermissionCode: string
{
    case ViewAdminPanel = 'view_admin_panel';
    case ViewMetrics = 'view_metrics';
    case ViewRevenue = 'view_revenue';
    case ManageStaff = 'manage_staff';
    case ManageAgencies = 'manage_agencies';
    case ResetPassword = 'reset_password';
    case ManagePlans = 'manage_plans';
}
