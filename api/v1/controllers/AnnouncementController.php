<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\DB;
use ACA\Api\Core\Response;
use ACA\Api\Core\Auth;
use ACA\Api\Middleware\PermissionMiddleware;

final class AnnouncementController
{
  public static function index(): void
  {
    $payload = Auth::user();
    PermissionMiddleware::require('manage_announcements', $payload);

    $pdo = DB::conn();

    $stmt = $pdo->query("
      SELECT *
      FROM announcements
      ORDER BY created_at DESC
    ");

    Response::ok($stmt->fetchAll(), 'Announcements fetched');
  }
}
