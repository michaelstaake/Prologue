<?php
require_once __DIR__ . '/../../../../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../../../vendor/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../../../vendor/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailTwoFAProvider implements TwoFAProviderInterface {
    public function getName(): string {
        return 'email';
    }

    public function getLabel(): string {
        return 'Email';
    }

    public function isAvailable(int $userId): bool {
        $mailHost = trim((string)(Setting::get('mail_host') ?? ''));
        $mailUser = trim((string)(Setting::get('mail_user') ?? ''));
        return $mailHost !== '' && $mailUser !== '';
    }

    public function sendChallenge(int $userId, string $ip): bool {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        Database::query(
            "INSERT INTO twofa_codes (user_id, code, ip, expires_at) VALUES (?, ?, ?, ?)",
            [$userId, $code, $ip, $expires]
        );

        return $this->sendEmail(
            $user->email,
            'Your Prologue 2FA Code',
            "Your login code is <b>{$code}</b> (valid 10 minutes)."
        );
    }

    public function verifyCode(int $userId, string $code, string $ip): bool {
        $row = Database::query(
            "SELECT * FROM twofa_codes WHERE user_id = ? AND code = ? AND expires_at > NOW() AND ip = ?",
            [$userId, $code, $ip]
        )->fetch();

        return (bool)$row;
    }

    public function cleanup(int $userId): void {
        Database::query("DELETE FROM twofa_codes WHERE user_id = ?", [$userId]);
    }

    private function sendEmail(string $to, string $subject, string $body): bool {
        $mailHost = (string)(Setting::get('mail_host') ?? '');
        $mailUser = (string)(Setting::get('mail_user') ?? '');

        if ($mailHost === '' || $mailUser === '') {
            return false;
        }

        $mailPort = (int)(Setting::get('mail_port') ?? 587);
        $mailPass = (string)(Setting::get('mail_pass') ?? '');
        $mailFrom = (string)(Setting::get('mail_from') ?? '');
        $mailFromName = (string)(Setting::get('mail_from_name') ?? '');

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $mailHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailUser;
            $mail->Password = $mailPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailPort;

            $mail->setFrom($mailFrom, $mailFromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
