<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up your password</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            color: #e2e8f0;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .email-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #94a3b8;
            font-size: 16px;
            margin: 0;
        }
        .content {
            margin-bottom: 32px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #f1f5f9;
        }
        .message {
            font-size: 16px;
            color: #cbd5e1;
            margin-bottom: 24px;
        }
        .token-container {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            border: 2px solid #6366f1;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 24px 0;
            position: relative;
            overflow: hidden;
        }
        .token-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.1), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        .token-label {
            font-size: 14px;
            color: #a5b4fc;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .token {
            font-size: 24px;
            font-weight: bold;
            color: #f1f5f9;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            word-break: break-all;
            position: relative;
            z-index: 1;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #ffffff;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px 0 rgba(249, 115, 22, 0.3);
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px 0 rgba(249, 115, 22, 0.4);
        }
        .footer {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
        }
        .footer-text {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }
        .accent-text {
            color: #10b981;
            font-weight: 600;
        }
        .warning-text {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="email-card">
            <div class="header">
                <div class="logo">StreamBoost</div>
                <p class="subtitle">Elevate Your Streaming Experience</p>
            </div>
            
            <div class="content">
                <h1 class="greeting">Welcome to StreamBoost! 🚀</h1>
                
                <p class="message">
                    You're just one step away from unlocking your streaming potential! We've created your account and now you need to set up your password to get started.
                </p>
                
                <div class="token-container">
                    <div class="token-label">Your Setup Token</div>
                    <div class="token">{{ $token }}</div>
                </div>
                
                <p class="message">
                    Use this token to complete your account setup and create your secure password. This token will expire in <span class="accent-text">24 hours</span> for your security.
                </p>
                
                <div style="text-align: center; margin: 32px 0;">
                    <a href="{{ config('app.frontend_url') }}/setup-password?token={{ $token }}" class="cta-button">
                        Complete Setup
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <p class="footer-text">
                    <span class="warning-text">Security Notice:</span> If you didn't request this account setup, please ignore this email. This token will expire automatically.
                </p>
                <p class="footer-text" style="margin-top: 16px;">
                    Need help? Contact our support team at <a href="mailto:support@smmview.live" style="color: #f97316;">support@smmview.live</a>
                </p>
            </div>
        </div>
    </div>
</body>

</html>