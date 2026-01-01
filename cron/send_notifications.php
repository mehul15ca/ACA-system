<?php
require __DIR__ . '/../includes/cron_guard.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../email/EmailRenderer.php';
require_once __DIR__ . '/../email/Mailer.php';

$renderer = new EmailRenderer();
$mailer = new Mailer();

$q = $conn->query("
    SELECT * FROM notifications_queue 
    WHERE status='pending' 
    ORDER BY id ASC 
    LIMIT 20
");

while ($row = $q->fetch_assoc()) {

    $template = $row['template_code'];
    $email = $row['receiver_email'];
    $cc = $row['cc_email']; // we already added this field earlier
    $payload = json_decode($row['payload_json'], true);

    $render = $renderer->render($template, $payload);
    $subject = $render['subject'];
    $html = $render['html'];

    $result = $mailer->send($email, $subject, $html, $cc);

    if ($result === true) {
        $conn->query("
            UPDATE notifications_queue 
            SET status='sent', sent_at=NOW() 
            WHERE id={$row['id']}
        ");
    } else {
        $err = $conn->real_escape_string($result);
        $conn->query("
            UPDATE notifications_queue 
            SET status='failed', error_message='$err'
            WHERE id={$row['id']}
        ");
        $conn->query("
            INSERT INTO error_logs (user_id, error_message, file, line, context)
            VALUES (NULL, '$err', 'send_notifications.php', 0, 'Email sending failure')
        ");
    }
}

echo "Cron executed\n";
