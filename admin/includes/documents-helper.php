<?php
// admin/includes/documents-helper.php

if (!function_exists('renderDocumentsSection')) {

    function extractDriveFileId($input) {
        // Accept either pure file ID or a full Drive URL
        $input = trim($input);
        if ($input === '') return '';

        // If looks like a URL
        if (strpos($input, 'http') === 0) {
            // Try patterns like /d/FILE_ID/
            if (preg_match('#/d/([^/]+)/?#', $input, $m)) {
                return $m[1];
            }
            // Try ?id=FILE_ID
            if (preg_match('#[?&]id=([^&]+)#', $input, $m)) {
                return $m[1];
            }
        }

        // Otherwise assume it's already a file ID
        return $input;
    }

    /**
     * Renders a Documents section for given owner (student / coach).
     *
     * @param mysqli $conn
     * @param string $ownerType 'student' or 'coach'
     * @param int    $ownerId
     * @param bool   $canManage (show Add form + Delete buttons)
     */
    function renderDocumentsSection($conn, $ownerType, $ownerId, $canManage = false) {
        $ownerTypeSafe = $conn->real_escape_string($ownerType);
        $ownerId = (int)$ownerId;

        $docs = [];
        $sql = "SELECT id, title, file_type, drive_file_id, created_at
                FROM documents
                WHERE owner_type = '$ownerTypeSafe' AND owner_id = $ownerId
                ORDER BY created_at DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $docs[] = $row;
            }
        }

        $label = ($ownerType === 'coach') ? 'Coach Documents' : 'Student Documents';
        ?>
        <div class="card" style="margin-top:16px;">
            <h2 style="margin-top:0;"><?php echo htmlspecialchars($label); ?></h2>

            <?php if ($canManage): ?>
                <form action="upload-document.php" method="POST" style="margin-bottom:16px;">
                    <input type="hidden" name="owner_type" value="<?php echo htmlspecialchars($ownerType); ?>">
                    <input type="hidden" name="owner_id" value="<?php echo (int)$ownerId; ?>">

                    <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
                        <div style="flex:1 1 220px;">
                            <label style="font-size:13px; color:#4B5563;">Title</label>
                            <input type="text" name="title"
                                   style="width:100%; padding:6px 8px; border-radius:6px; border:1px solid #D1D5DB;"
                                   placeholder="e.g. Signed Waiver Form" required>
                        </div>
                        <div style="flex:1 1 260px;">
                            <label style="font-size:13px; color:#4B5563;">Google Drive File ID or URL</label>
                            <input type="text" name="drive_input"
                                   style="width:100%; padding:6px 8px; border-radius:6px; border:1px solid #D1D5DB;"
                                   placeholder="Paste Drive link or File ID" required>
                        </div>
                        <div>
                            <button type="submit" class="button-primary">Add Document</button>
                        </div>
                    </div>

                    <p style="margin-top:6px; font-size:11px; color:#6B7280;">
                        Upload your file to Google Drive, make it viewable, then paste the share link or File ID here.
                        The system will automatically extract the correct ID for preview.
                    </p>
                </form>
            <?php endif; ?>

            <?php if (empty($docs)): ?>
                <p style="font-size:13px; color:#6B7280; margin:0;">
                    No documents found for this profile.
                </p>
            <?php else: ?>
                <table class="table" style="width:100%; border-collapse:collapse; margin-top:8px;">
                    <thead>
                    <tr style="text-align:left; font-size:13px; color:#6B7280; border-bottom:1px solid #E5E7EB;">
                        <th style="padding:6px 4px;">Title</th>
                        <th style="padding:6px 4px;">Type</th>
                        <th style="padding:6px 4px;">Created</th>
                        <th style="padding:6px 4px;">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($docs as $d): ?>
                        <?php
                        $driveId = $d['drive_file_id'];
                        $previewUrl = "https://drive.google.com/file/d/" . urlencode($driveId) . "/preview";
                        $openUrl = "https://drive.google.com/file/d/" . urlencode($driveId) . "/view";
                        ?>
                        <tr style="font-size:13px; border-bottom:1px solid #F3F4F6;">
                            <td style="padding:6px 4px;"><?php echo htmlspecialchars($d['title']); ?></td>
                            <td style="padding:6px 4px;"><?php echo htmlspecialchars($d['file_type'] ?? ''); ?></td>
                            <td style="padding:6px 4px;"><?php echo htmlspecialchars($d['created_at']); ?></td>
                            <td style="padding:6px 4px;">
                                <button type="button"
                                        class="button-secondary doc-preview-btn"
                                        data-preview-url="<?php echo htmlspecialchars($previewUrl); ?>"
                                        style="padding:4px 8px; font-size:12px;">
                                    Preview
                                </button>
                                <a href="<?php echo htmlspecialchars($openUrl); ?>"
                                   target="_blank"
                                   class="button-secondary"
                                   style="padding:4px 8px; font-size:12px; text-decoration:none; margin-left:4px;">
                                    Open in Drive
                                </a>
                                <?php if ($canManage): ?>
                                    <a href="delete-document.php?id=<?php echo (int)$d['id']; ?>&owner_type=<?php echo urlencode($ownerType); ?>&owner_id=<?php echo (int)$ownerId; ?>"
                                       class="button-danger"
                                       style="padding:4px 8px; font-size:12px; text-decoration:none; margin-left:4px;"
                                       onclick="return confirm('Delete this document?');">
                                        Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Simple preview modal -->
        <div id="docPreviewModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
            <div style="background:#FFFFFF; width:90%; max-width:900px; height:80%; border-radius:10px; overflow:hidden; display:flex; flex-direction:column;">
                <div style="padding:8px 12px; background:#003566; color:#FFFFFF; font-size:14px; display:flex; justify-content:space-between; align-items:center;">
                    <span>Document Preview</span>
                    <button type="button" id="docPreviewCloseBtn" style="background:none; border:none; color:#FFFFFF; font-size:18px; cursor:pointer;">×</button>
                </div>
                <div style="flex:1;">
                    <iframe id="docPreviewFrame"
                            src=""
                            style="border:0; width:100%; height:100%;"
                            allow="autoplay"></iframe>
                </div>
            </div>
        </div>

        <script>
            (function() {
                const modal = document.getElementById('docPreviewModal');
                const frame = document.getElementById('docPreviewFrame');
                const closeBtn = document.getElementById('docPreviewCloseBtn');

                if (!modal || !frame || !closeBtn) return;

                document.querySelectorAll('.doc-preview-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const url = this.getAttribute('data-preview-url');
                        frame.src = url;
                        modal.style.display = 'flex';
                    });
                });

                closeBtn.addEventListener('click', function() {
                    frame.src = '';
                    modal.style.display = 'none';
                });

                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        frame.src = '';
                        modal.style.display = 'none';
                    }
                });
            })();
        </script>
        <?php
    }
}
