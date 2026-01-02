<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\Auth;
use ACA\Api\Core\DB;

final class ProfileController extends BaseController
{
    public static function me(): void
    {
        $user = Auth::user();
        $pdo = DB::conn();

        $stmt = $pdo->prepare("
            SELECT id, username, role, status
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$user['user_id']]);
        $row = $stmt->fetch();

        self::ok(['profile' => $row], 'Profile loaded');
    }
}
