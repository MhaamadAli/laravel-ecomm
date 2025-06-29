<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #EF4444;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .alert-box {
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
            border-left: 4px solid #EF4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
        }
        .btn {
            display: inline-block;
            background-color: #EF4444;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: bold;
            text-align: center;
        }
        .btn:hover {
            background-color: #DC2626;
        }
        .security-info {
            background-color: #F0F9FF;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #3B82F6;
            margin: 20px 0;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }
        .divider {
            border-top: 1px solid #e5e7eb;
            margin: 30px 0;
        }
        .small-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔒 Password Reset Request</h1>
    </div>

    <div class="content">
        <p>Hi {{ $user->name }},</p>
        
        <p>We received a request to reset the password for your account associated with this email address.</p>

        <div class="alert-box">
            <strong>⚠️ Important:</strong> If you did not request a password reset, please ignore this email. Your password will remain unchanged.
        </div>

        <p>To reset your password, click the button below:</p>

        <center>
            <a href="{{ $resetUrl }}" class="btn">Reset My Password</a>
        </center>

        <div class="security-info">
            <h3 style="margin-top: 0;">🛡️ Security Information</h3>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>This password reset link is valid for <strong>60 minutes</strong></li>
                <li>After using this link, it will become invalid</li>
                <li>For your security, we recommend using a strong, unique password</li>
                <li>Never share your password with anyone</li>
            </ul>
        </div>

        <div class="divider"></div>

        <p><strong>Can't click the button?</strong> Copy and paste this link into your browser:</p>
        <p style="word-break: break-all; background-color: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;">
            {{ $resetUrl }}
        </p>

        <div class="small-text">
            <p><strong>Need help?</strong> If you're having trouble resetting your password, please contact our customer support team.</p>
        </div>

        <p>Best regards,<br>
        The E-Commerce Security Team</p>
    </div>

    <div class="footer">
        <p>This is an automated security message. Please do not reply to this email.</p>
        <p>If you have any questions, please contact our customer support.</p>
        <div style="margin-top: 20px; font-size: 11px;">
            <p>© {{ date('Y') }} E-Commerce Platform. All rights reserved.</p>
            <p>This email was sent to {{ $user->email }}</p>
        </div>
    </div>
</body>
</html>