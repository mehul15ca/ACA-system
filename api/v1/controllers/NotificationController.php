<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\DB;
use ACA\Api\Core\Response;
use ACA\Api\Core\Auth;

final class NotificationController
{
  public static function index(): void
  {
    $payload = Auth::user();

    $pdo = DB::conn();

    $stmt = $pdo->prepare("
      SELECT *
      FROM notifications
      WHERE user_id = ?
      ORDER BY created_at DESC
    ");
    $stmt->execute([$payload['user_id']]);

    Response::ok($stmt->fetchAll(), 'Notifications fetched');
  }
}
