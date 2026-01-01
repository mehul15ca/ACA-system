<?php
// Public widget: Top 5 Players
// Can be included on your public homepage: include "public-top-players.php";

include __DIR__ . "/config.php";

// Load top players with student + batch
$sql = "
    SELECT tp.rank_position, tp.highlight_text,
           s.id AS student_id, s.first_name, s.last_name, s.admission_no,
           s.profile_photo_drive_id,
           b.name AS batch_name
    FROM top_players tp
    JOIN students s ON s.id = tp.student_id
    LEFT JOIN batches b ON b.id = s.batch_id
    WHERE tp.active = 1
    ORDER BY tp.rank_position ASC
";
$res = $conn->query($sql);
$players = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $players[] = $row;
    }
}
?>
<?php if ($players): ?>
<style>
    .aca-topplayers-section {
        padding:24px 16px;
        background:#020617;
        color:#e5e7eb;
        max-width:1100px;
        margin:0 auto;
    }
    .aca-topplayers-header {
        display:flex;
        justify-content:space-between;
        align-items:flex-end;
        gap:12px;
        margin-bottom:16px;
    }
    .aca-topplayers-title {
        font-size:20px;
        font-weight:600;
    }
    .aca-topplayers-sub {
        font-size:12px;
        color:#9ca3af;
    }
    .aca-topplayers-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
        gap:12px;
    }
    .aca-topplayer-card {
        background:#020617;
        border-radius:16px;
        border:1px solid #1f2937;
        padding:12px;
        display:flex;
        gap:10px;
        align-items:flex-start;
    }
    .aca-topplayer-avatar {
        width:56px;
        height:56px;
        border-radius:999px;
        overflow:hidden;
        flex-shrink:0;
        background:#0f172a;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:18px;
        font-weight:600;
        color:#38bdf8;
    }
    .aca-topplayer-avatar img {
        width:100%;
        height:100%;
        object-fit:cover;
        display:block;
    }
    .aca-topplayer-body {
        flex:1;
        min-width:0;
    }
    .aca-topplayer-name {
        font-size:14px;
        font-weight:600;
    }
    .aca-topplayer-batch {
        font-size:11px;
        color:#9ca3af;
        margin-bottom:4px;
    }
    .aca-topplayer-highlight {
        font-size:12px;
        margin-bottom:6px;
    }
    .aca-topplayer-meta {
        display:flex;
        justify-content:space-between;
        align-items:center;
        font-size:11px;
        color:#64748b;
    }
    .aca-topplayer-rank {
        font-size:11px;
        padding:2px 8px;
        border-radius:999px;
        border:1px solid #1f2937;
    }
    .aca-topplayer-link {
        font-size:11px;
        color:#22c55e;
        text-decoration:none;
    }
    .aca-topplayer-link:hover {
        text-decoration:underline;
    }

    @media (max-width:640px) {
        .aca-topplayers-header {
            flex-direction:column;
            align-items:flex-start;
        }
    }
</style>

<section class="aca-topplayers-section">
    <div class="aca-topplayers-header">
        <div>
            <div class="aca-topplayers-title">Top Players</div>
            <div class="aca-topplayers-sub">Handpicked talents from Australasia Cricket Academy</div>
        </div>
    </div>

    <div class="aca-topplayers-grid">
        <?php foreach ($players as $p): ?>
            <?php
            $name = trim($p['first_name']." ".$p['last_name']);
            $initials = "";
            $parts = preg_split('/\s+/', $name);
            if ($parts) {
                foreach ($parts as $idx=>$pt) {
                    if ($idx > 1) break;
                    $initials .= mb_substr($pt, 0, 1);
                }
            }
            $photoId = $p['profile_photo_drive_id'];
            $photoUrl = "";
            if (!empty($photoId)) {
                // Assume this is a Google Drive file ID
                $photoUrl = "https://drive.google.com/uc?export=view&id=" . urlencode($photoId);
            }
            ?>
            <div class="aca-topplayer-card">
                <div class="aca-topplayer-avatar">
                    <?php if ($photoUrl): ?>
                        <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                    <?php else: ?>
                        <?php echo htmlspecialchars($initials ?: "P"); ?>
                    <?php endif; ?>
                </div>
                <div class="aca-topplayer-body">
                    <div class="aca-topplayer-name">
                        <?php echo htmlspecialchars($name); ?>
                    </div>
                    <div class="aca-topplayer-batch">
                        <?php if (!empty($p['batch_name'])): ?>
                            Batch: <?php echo htmlspecialchars($p['batch_name']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="aca-topplayer-highlight">
                        <?php echo htmlspecialchars($p['highlight_text']); ?>
                    </div>
                    <div class="aca-topplayer-meta">
                        <span class="aca-topplayer-rank">Rank #<?php echo (int)$p['rank_position']; ?></span>
                        <a class="aca-topplayer-link"
                           href="<?php echo 'admin/view-student.php?id='.(int)$p['student_id']; ?>">
                            View profile
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
