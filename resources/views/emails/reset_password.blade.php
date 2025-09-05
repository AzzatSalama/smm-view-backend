<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your password</title>
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
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #94a3b8;
            font-size: 16px;
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
            color: #cbd5e1;
            margin-bottom: 24px;
            font-size: 16px;
        }
        .token-section {
            background: rgba(59, 130, 246, 0.1);
            border: 2px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 24px 0;
        }
        .token-label {
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .token {
            font-family: 'Courier New', monospace;
            font-size: 32px;
            font-weight: bold;
            color: #3b82f6;
            letter-spacing: 4px;
            text-shadow: 0 0 10px rgba(59, 130, 246, 0.3);
        }
        .instructions {
            background: rgba(251, 146, 60, 0.1);
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 0 8px 8px 0;
            margin: 24px 0;
        }
        .instructions-title {
            color: #fbbf24;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .instructions-text {
            color: #fde68a;
            font-size: 14px;
            line-height: 1.5;
        }
        .footer {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
            padding-top: 24px;
        }
        .warning {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }
        .warning-text {
            color: #fca5a5;
            font-size: 14px;
            text-align: center;
        }
        .brand-colors {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #06b6d4 100%);
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
                <div class="logo">SMM VIEWERS</div>
                <div class="subtitle">Premium Streaming Solutions</div>
            </div>
            
            <div class="content">
                <div class="greeting">Reset Your Password</div>
                
                <div class="message">
                    We received a request to reset your password. Use the verification code below to reset your password and regain access to your account.
                </div>
                
                <div class="token-section">
                    <div class="token-label">Verification Code</div>
                    <div class="token">{{ $token }}</div>
                </div>
                
                <div class="instructions">
                    <div class="instructions-title">How to reset your password:</div>
                    <div class="instructions-text">
                        1. Go to the password reset page<br>
                        2. Enter your email address<br>
                        3. Enter the 6-digit code above<br>
                        4. Create your new password<br>
                        5. Start boosting your streams!
                    </div>
                </div>
                
                <div class="warning">
                    <div class="warning-text">
                        <strong>Security Notice:</strong> This code expires in 60 minutes. 
                        If you didn't request a password reset, please ignore this email.
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>This email was sent by <span class="brand-colors">SMM Viewers</span></p>
                <p>Questions? Contact our support team for assistance.</p>
            </div>
        </div>
    </div>
</body>
</html>
