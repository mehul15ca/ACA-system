<?php
include "../config.php";
checkLogin();
requireSuperadmin();

// Fetch users with linked coach/student names
$sql = "SELECT u.id, u.username, u.role, u.status, u.coach_id, u.student_id,
               c.name AS coach_name,
               s.first_name, s.last_name
        FROM users u
        LEFT JOIN coaches c ON u.coach_id = c.id
        LEFT JOIN students s ON u.student_id = s.id
        ORDER BY u.id DESC";
$result = $conn->query($sql);
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Users</h1>

<div class="table-card">
    <div class="table-header">
        <h2>All Users</h2>
        <a href="add-user.php" class="button">➕ Add User</a>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Linked To</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo htmlspecialchars($row['role']); ?></td>
                <td>
                    <?php
                    if ($row['role'] === 'coach' && $row['coach_id']) {
                        echo "Coach: " . htmlspecialchars($row['coach_name'] ?? ('#'.$row['coach_id']));
                    } elseif ($row['role'] === 'student' && $row['student_id']) {
                        $full = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                        echo "Student: " . htmlspecialchars($full !== '' ? $full : ('#'.$row['student_id']));
                    } else {
                        echo "-";
                    }
                    ?>
                </td>
                <td>
                    <span class="badge <?php echo $row['status'] === 'active' ? 'green' : ''; ?>">
                        <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                </td>
                <td>
                    <a class="text-link" href="edit-user.php?id=<?php echo $row['id']; ?>">Edit</a>
                    |
                    <a class="text-link" href="reset-password.php?id=<?php echo $row['id']; ?>">Reset Password</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
