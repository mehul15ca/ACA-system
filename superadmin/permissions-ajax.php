<?php
include "../config.php";
checkLogin();
requireSuperadmin();

header('Content-Type: application/json');

$mode = $_POST['mode'] ?? '';

if ($mode === 'toggle_role') {
    $role = $_POST['role'] ?? '';
    $permission_id = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;
    $allowed = isset($_POST['allowed']) ? (int)$_POST['allowed'] : 0;

    $validRoles = ['superadmin','admin','coach','student'];
    if (!in_array($role, $validRoles)) {
        echo json_encode(['success' => false, 'message' => 'Invalid role']);
        exit;
    }
    if ($permission_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid permission id']);
        exit;
    }

    if ($role === 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Superadmin always has all permissions']);
        exit;
    }

    if ($allowed) {
        $stmt = $conn->prepare("
            INSERT INTO role_permissions (role, permission_id)
            SELECT ?, ?
            WHERE NOT EXISTS (
                SELECT 1 FROM role_permissions WHERE role = ? AND permission_id = ?
            )
        ");
        $stmt->bind_param("sisi", $role, $permission_id, $role, $permission_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    } else {
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role = ? AND permission_id = ?");
        $stmt->bind_param("si", $role, $permission_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }
}

if ($mode === 'toggle_user') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $permission_id = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;
    $allowed = isset($_POST['allowed']) ? (int)$_POST['allowed'] : 0;

    if ($user_id <= 0 || $permission_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    if ($allowed) {
        $stmt = $conn->prepare("
            INSERT INTO user_permissions (user_id, permission_id)
            SELECT ?, ?
            WHERE NOT EXISTS (
                SELECT 1 FROM user_permissions WHERE user_id = ? AND permission_id = ?
            )
        ");
        $stmt->bind_param("iiii", $user_id, $permission_id, $user_id, $permission_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    } else {
        $stmt = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?");
        $stmt->bind_param("ii", $user_id, $permission_id);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $ok]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid mode']);
