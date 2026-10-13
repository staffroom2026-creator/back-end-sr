<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Password Reset - StaffRoom</title>
</head>
<body style="margin: 0; padding: 20px; font-family: 'Arial', 'Helvetica', sans-serif; background-color: #f4f4f4; color: #333333;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <div style="text-align: center; padding: 25px 20px; background-color: #00466a; color: #ffffff;">
            <h1 style="margin: 0; font-size: 24px; letter-spacing: 1px;">StaffRoom</h1>
            <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;">Password Reset Request</p>
        </div>
        <div style="padding: 30px; font-size: 15px; line-height: 1.6; color: #4a5568;">
            <p style="margin-top: 0;">Hello <strong style="color: #2b6cb0;">{USERNAME}</strong>,</p>
            <p>We received a request to reset your StaffRoom password. Use the verification code below to continue:</p>
            <div style="display: flex; justify-content: center; align-items: center; margin: 25px 0;">
                <div style="width: 85%; max-width: 300px; height: 50px; line-height: 50px; background-color: #d69e2e; text-align: center; font-family: 'Courier New', Courier, monospace; font-size: 28px; font-weight: bold; color: #ffffff; border-radius: 6px; letter-spacing: 6px;">
                    {CODE}
                </div>
            </div>
            <div style="background-color: #fffaf0; border-left: 4px solid #dd6b20; padding: 15px; margin: 20px 0; font-size: 14px; color: #7b341e;">
                This code helps you securely reset your password.<br>
                <strong>Security notice:</strong> Never share this code with anyone, including StaffRoom support staff.
            </div>
            <p style="font-size: 13px; color: #718096; margin-top: 25px;">
                If you did not request this change, please update your account security immediately and contact support if needed.
            </p>
        </div>
        <div style="text-align: center; padding: 15px; background-color: #2d3748; color: #cbd5e0; font-size: 12px;">
            <p style="margin: 0;">Welcome to Nigeria's Premier Teaching Marketplace,</p>
            <p style="margin: 3px 0 0 0; font-weight: bold; color: #ffffff;">The StaffRoom Team</p>
        </div>
    </div>
</body>
</html>