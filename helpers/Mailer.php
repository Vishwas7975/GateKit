<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    /**
     * Send a 6-digit OTP password-reset email to the given address.
     * Returns true on success, false on failure.
     */
    public static function sendOtp(string $toEmail, string $toName, string $otp): bool
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        $mail = new PHPMailer(true);

        try {
            // ── Server settings ─────────────────────────────────────────────
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = MAIL_PORT;

            // ── Sender / recipient ───────────────────────────────────────────
            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            // ── Content ──────────────────────────────────────────────────────
            $mail->isHTML(true);
            $mail->Subject = 'Your ' . APP_NAME . ' password reset code';
            $mail->Body    = self::htmlTemplate($otp);
            $mail->AltBody = "Your " . APP_NAME . " password reset code is: {$otp}\n\nThis code expires in 10 minutes.\nIf you did not request this, please ignore this email.";

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log('Mailer error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    // ─── HTML email template ────────────────────────────────────────────────
    private static function htmlTemplate(string $otp): string
    {
        $appName = htmlspecialchars(APP_NAME, ENT_QUOTES);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <body style="font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f8fafc;padding:30px;margin:0;color:#0f172a;">
            <div style="max-width:480px;margin:0 auto;background:#ffffff;padding:36px;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 4px 20px rgba(0,0,0,0.06);">

                <div style="text-align:center;margin-bottom:24px;">
                    <h1 style="margin:0;font-size:26px;color:#4f46e5;letter-spacing:-0.5px;">{$appName}</h1>
                    <p style="margin:4px 0 0;font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:1.5px;">Password Reset</p>
                </div>

                <hr style="border:none;border-top:1px solid #f1f5f9;margin:0 0 24px;">

                <p style="font-size:15px;line-height:1.6;color:#334155;margin:0 0 8px;">Hi there,</p>
                <p style="font-size:15px;line-height:1.6;color:#334155;margin:0 0 28px;">
                    We received a request to reset your {$appName} password. Use the one-time code below — it expires in <strong>10 minutes</strong>.
                </p>

                <div style="text-align:center;margin:0 0 28px;">
                    <span style="display:inline-block;font-size:38px;font-weight:800;letter-spacing:10px;color:#4f46e5;background:#eef2ff;padding:14px 32px;border-radius:10px;border:2px dashed #c7d2fe;">
                        {$otp}
                    </span>
                </div>

                <p style="font-size:13px;color:#64748b;line-height:1.6;margin:0 0 24px;">
                    If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
                </p>

                <hr style="border:none;border-top:1px solid #f1f5f9;margin:0 0 20px;">

                <p style="font-size:11px;text-align:center;color:#94a3b8;margin:0;">
                    &copy; {$appName}
                </p>
            </div>
        </body>
        </html>
        HTML;
    }
}
