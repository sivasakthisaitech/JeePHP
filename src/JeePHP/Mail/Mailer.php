<?php
declare(strict_types=1);

namespace JeePHP\Mail;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Mailer
{
    public static function sendView(string $to, string $subject, string $view, array $data = []): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid recipient email address is required.');
        }
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new RuntimeException('MailO needs PHPMailer. Run composer update after activating MailO.');
        }
        $html = self::render($view, $data);
        $config = require dirname(__DIR__, 3) . '/config/mail.php';
        if (($config['host'] ?? '') === '' || ($config['from_address'] ?? '') === '') {
            throw new RuntimeException('Set MAIL_HOST and MAIL_FROM_ADDRESS in .duck before sending email.');
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) $config['host'];
            $mail->Port = (int) $config['port'];
            $mail->SMTPAuth = (string) ($config['username'] ?? '') !== '';
            $mail->Username = (string) ($config['username'] ?? '');
            $mail->Password = (string) ($config['password'] ?? '');
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;
            $mail->SMTPKeepAlive = false;
            $mail->SMTPSecure = match (strtolower((string) ($config['encryption'] ?? 'tls'))) {
                'tls' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS,
                'ssl' => \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS,
                'none', '' => '',
                default => throw new RuntimeException('MAIL_ENCRYPTION must be tls, ssl, or none.'),
            };
            $mail->setFrom((string) $config['from_address'], (string) ($config['from_name'] ?? 'JeePHP'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
            return $mail->send();
        } catch (Throwable $error) {
            throw new RuntimeException('Email could not be sent. Check the SMTP host, port, encryption, and credentials in .duck.', 0, $error);
        }
    }

    private static function render(string $view, array $data): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/', $view)) {
            throw new InvalidArgumentException('Invalid email view name.');
        }
        $path = dirname(__DIR__, 3) . '/app/Views/' . $view . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('Email view not found: ' . $view);
        }
        ob_start();
        try {
            view($view, $data);
            return (string) ob_get_clean();
        } catch (Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
