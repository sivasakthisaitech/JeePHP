<!doctype html>
<html lang="en">
<body>
    <h1>Password reset request</h1>
    <p>Hello <?= e($name ?? 'there') ?>. Use the link below within one hour to set a new password.</p>
    <p><a href="<?= e($resetUrl) ?>">Reset password</a></p>
    <p>If you did not request this, ignore this email.</p>
</body>
</html>
