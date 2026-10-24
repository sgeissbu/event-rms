<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Env;
use App\Core\Settings;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Mailversand über SMTP (falls SMTP_HOST in .env gesetzt), sonst PHP mail().
 * Fehler werden geloggt, nie an Besucher durchgereicht.
 */
final class Mailer
{
    public static function isConfigured(): bool
    {
        return (string) Settings::get('mail_from') !== '';
    }

    public static function send(string $to, string $subject, string $html, string $text): bool
    {
        if (!self::isConfigured()) {
            return false;
        }
        $mail = new PHPMailer(true);
        try {
            $host = (string) Env::get('SMTP_HOST', '');
            if ($host !== '') {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = (int) Env::get('SMTP_PORT', '465');
                $mail->SMTPAuth = Env::get('SMTP_USER', '') !== '';
                $mail->Username = (string) Env::get('SMTP_USER', '');
                $mail->Password = (string) Env::get('SMTP_PASS', '');
                $mail->SMTPSecure = Env::get('SMTP_SECURE', 'ssl') === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
                $mail->Timeout = 10;
            }
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom((string) Settings::get('mail_from'), (string) (Settings::get('mail_from_name') ?: Settings::get('club_name')));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;
            $mail->send();
            return true;
        } catch (MailException $e) {
            error_log('event-rms Mail an ' . $to . ' fehlgeschlagen: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
