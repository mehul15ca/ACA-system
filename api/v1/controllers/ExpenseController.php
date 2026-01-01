<?php
declare(strict_types=1);

namespace ACA\Api\Controllers;

use ACA\Api\Core\DB;
use ACA\Api\Core\Response;
use ACA\Api\Core\Paginator;
use ACA\Api\Core\Auth;
use ACA\Api\Middleware\PermissionMiddleware;

final class ExpenseController
{
  public static function index(): void
  {
    $payload = Auth::user();
    PermissionMiddleware::require('manage_expenses', $payload);

    $pdo = DB::conn();
    $pg = Paginator::fromQuery();

    $stmt = $pdo->prepare("
      SELECT SQL_CALC_FOUND_ROWS *
      FROM expenses
      ORDER BY id DESC
      LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $pg['limit'], \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pg['offset'], \PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    $total = (int)$pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

    Response::ok($rows, 'Expenses fetched', [
      'page' => $pg['page'],
      'limit' => $pg['limit'],
      'total' => $total
    ]);
  }
}
