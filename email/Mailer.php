<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

class Mailer
{
    private PHPMailer $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);

        // Load SMTP config from environment
        $host   = $_ENV['ACA_SMTP_HOST']   ?? getenv('ACA_SMTP_HOST');
        $user   = $_ENV['ACA_SMTP_USER']   ?? getenv('ACA_SMTP_USER');
        $pass   = $_ENV['ACA_SMTP_PASS']   ?? getenv('ACA_SMTP_PASS');
        $port   = (int)($_ENV['ACA_SMTP_PORT'] ?? getenv('ACA_SMTP_PORT') ?? 587);
        $secure = $_ENV['ACA_SMTP_SECURE'] ?? getenv('ACA_SMTP_SECURE') ?? PHPMailer::ENCRYPTION_STARTTLS;

        if (!$host || !$user || !$pass) {
            throw new Exception(
                "SMTP is not configured. Set ACA_SMTP_HOST, ACA_SMTP_USER, ACA_SMTP_PASS."
            );
        }

        // SMTP setup
        $this->mail->isSMTP();
        $this->mail->Host       = $host;
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $user;
        $this->mail->Password   = $pass;
        $this->mail->Port       = $port;
        $this->mail->SMTPSecure = $secure;

        // Defaults
        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom(
            $_ENV['ACA_MAIL_FROM'] ?? 'no-reply@aca-system.local',
            $_ENV['ACA_MAIL_FROM_NAME'] ?? 'Australasia Cricket Academy'
        );
    }

    /**
     * Send an email
     *
     * @param string       $to
     * @param string       $subject
     * @param string       $html
     * @param string|null  $cc
     * @return bool
     * @throws Exception
     */
    public function send(string $to, string $subject, string $html, ?string $cc = null): bool
    {
        $this->mail->clearAddresses();
        $this->mail->clearCCs();

        $this->mail->addAddress($to);

        if ($cc) {
            $this->mail->addCC($cc);
        }

        $this->mail->isHTML(true);
        $this->mail->Subject = $subject;
        $this->mail->Body    = $html;

        return $this->mail->send();
    }
}
