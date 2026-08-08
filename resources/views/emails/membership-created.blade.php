<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Created</title>
    <style>
        body {
            font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #1a1a1a;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #5B2C8F 0%, #6e50d4 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .email-header h1 {
            color: #F1C13E;
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            color: #5B2C8F;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .content {
            color: #333333;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .membership-details {
            background-color: #f8f9fa;
            border-left: 4px solid #F1C13E;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .membership-details strong {
            color: #5B2C8F;
            display: inline-block;
            min-width: 160px;
        }
        .membership-details span {
            color: #666666;
        }
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #5B2C8F;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .button:hover {
            background-color: #4a2373;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 30px;
            text-align: center;
            color: #666666;
            font-size: 14px;
            border-top: 1px solid #e0e0e0;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Welcome! Your Membership Has Been Created</h1>
        </div>
        
        <div class="email-body">
            <div class="greeting">Hello {{ $user->name }}!</div>
            
            <div class="content">
                Your membership has been successfully created. We're excited to have you join us!
            </div>
            
            <div class="membership-details">
                <p style="margin: 0 0 12px 0;"><strong>Email:</strong> <span>{{ $user->email }}</span></p>
                <p style="margin: 0 0 12px 0;"><strong>Password:</strong> <span style="font-family: monospace; background-color: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #5B2C8F;">{{ $plainPassword }}</span></p>
                <p style="margin: 0 0 12px 0;"><strong>Membership Number:</strong> <span>{{ $membership->membership_number }}</span></p>
                <p style="margin: 0 0 12px 0;"><strong>Registration Date:</strong> <span>{{ $membership->registration_date->format('F d, Y') }}</span></p>
                <p style="margin: 0;"><strong>Expiration Date:</strong> <span>{{ $membership->expiration_date->format('F d, Y') }}</span></p>
            </div>
            
            <div class="content" style="background-color: #fff3cd; border-left: 4px solid #F1C13E; padding: 15px; border-radius: 4px; margin: 20px 0;">
                <strong style="color: #5B2C8F;">Important:</strong> Please save your login credentials. You can use your email and the password above to log in to your account.
            </div>
            
            <div class="content">
                Thank you for joining us! We look forward to serving you.
            </div>
            
            <div class="button-container">
                <a href="{{ url('/') }}" class="button">View Your Membership</a>
            </div>
        </div>
        
        <div class="footer">
            <p>If you have any questions, please don't hesitate to contact us.</p>
            <p style="margin-top: 15px; color: #999999; font-size: 12px;">
                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>

