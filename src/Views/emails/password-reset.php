<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="margin: 0; padding: 0; background-color: #1a1a1a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #1a1a1a; padding: 40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color: #2d2d2d; border-radius: 8px; overflow: hidden; max-width: 600px;">
                    <tr>
                        <td style="background-color: #0d6efd; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px;">Password Reset</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <p style="color: #e0e0e0; font-size: 16px; line-height: 1.5; margin: 0 0 16px;">
                                Hi <?= htmlspecialchars($userName) ?>,
                            </p>
                            <p style="color: #e0e0e0; font-size: 16px; line-height: 1.5; margin: 0 0 24px;">
                                We received a request to reset your password. Click the button below to choose a new one.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto 24px;">
                                <tr>
                                    <td style="background-color: #0d6efd; border-radius: 6px;">
                                        <a href="<?= htmlspecialchars($resetUrl) ?>" style="display: inline-block; padding: 14px 32px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600;">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="color: #aaaaaa; font-size: 14px; line-height: 1.5; margin: 0 0 16px;">
                                This link will expire in <?= (int) $expiryMinutes ?> minutes.
                            </p>
                            <p style="color: #aaaaaa; font-size: 14px; line-height: 1.5; margin: 0 0 16px;">
                                If the button doesn't work, copy and paste this URL into your browser:
                            </p>
                            <p style="color: #6ea8fe; font-size: 13px; word-break: break-all; margin: 0 0 24px;">
                                <?= htmlspecialchars($resetUrl) ?>
                            </p>
                            <hr style="border: none; border-top: 1px solid #444; margin: 24px 0;">
                            <p style="color: #888888; font-size: 13px; line-height: 1.5; margin: 0;">
                                If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
