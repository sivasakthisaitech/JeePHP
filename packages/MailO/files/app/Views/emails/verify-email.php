<!doctype html>
<html lang="en">
<body>
    <h1>Verify your email address</h1>
    <p>Hello <?= e($name ?? 'there') ?>. Verify your address within 24 hours:</p>
    <p><a href="<?= e($verificationUrl) ?>">Verify email</a></p>
</body>
</html>
