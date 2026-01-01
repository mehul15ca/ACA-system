<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

class Mailer {

    public function send($to, $subject, $html, $cc = null) {
        $mail = new PHPMailer(true);

        try {
            // SMTP SETTINGS — configure later
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'your-email@gmail.com';
            $mail->Password = 'your-email-password';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('no-reply@aca.com', 'Australasia Cricket Academy');
            $mail->addAddress($to);

            if ($cc) {
                $mail->addCC($cc);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;

            $mail->send();
            return true;

        } catch (Exception $e) {
            return $mail->ErrorInfo;
        }
    }
}
