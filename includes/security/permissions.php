<?php
// includes/security/permissions.php

declare(strict_types=1);

final class Permissions
{
    /**
     * Define permission keys once, use everywhere.
     * Keep keys stable because they may be referenced across files.
     */
    public const ADMIN_DASHBOARD   = 'admin.dashboard';
    public const STUDENTS_MANAGE   = 'students.manage';
    public const COACHES_MANAGE    = 'coaches.manage';
    public const BATCHES_MANAGE    = 'batches.manage';
    public const GROUNDS_MANAGE    = 'grounds.manage';
    public const SCHEDULES_MANAGE  = 'schedules.manage';
    public const MATCHES_MANAGE    = 'matches.manage';
    public const ATTENDANCE_VIEW   = 'attendance.view';
    public const FEES_MANAGE       = 'fees.manage';
    public const INVOICES_MANAGE   = 'invoices.manage';
    public const PAYMENTS_MANAGE   = 'payments.manage';
    public const ANNOUNCEMENTS_MANAGE = 'announcements.manage';
    public const EXPENSES_MANAGE   = 'expenses.manage';
    public const STORE_ORDERS_VIEW = 'store_orders.view';
    public const COACH_SALARIES_VIEW = 'coach_salaries.view';
    public const SUGGESTIONS_VIEW  = 'suggestions.view';

    /**
     * Role -> permissions mapping.
     * Adjust to your real roles. Keep it centralized.
     */
    public static function map(): array
    {
        return [
            'admin' => [
                self::ADMIN_DASHBOARD,
                self::STUDENTS_MANAGE,
                self::COACHES_MANAGE,
                self::BATCHES_MANAGE,
                self::GROUNDS_MANAGE,
                self::SCHEDULES_MANAGE,
                self::MATCHES_MANAGE,
                self::ATTENDANCE_VIEW,
                self::FEES_MANAGE,
                self::INVOICES_MANAGE,
                self::PAYMENTS_MANAGE,
                self::ANNOUNCEMENTS_MANAGE,
                self::EXPENSES_MANAGE,
                self::STORE_ORDERS_VIEW,
                self::COACH_SALARIES_VIEW,
                self::SUGGESTIONS_VIEW,
            ],
            // Example: restricted admin
            'staff' => [
                self::ADMIN_DASHBOARD,
                self::STUDENTS_MANAGE,
                self::ATTENDANCE_VIEW,
                self::STORE_ORDERS_VIEW,
            ],
        ];
    }

    public static function has(string $role, string $permission): bool
    {
        $map = self::map();
        if (!isset($map[$role]) || !is_array($map[$role])) {
            return false;
        }
        return in_array($permission, $map[$role], true);
    }
}
