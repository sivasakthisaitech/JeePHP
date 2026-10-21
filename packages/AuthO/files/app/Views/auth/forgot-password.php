<!doctype html>
<html lang="en"><meta charset="utf-8"><title>Reset password</title>
<body><main><h1>Reset your password</h1><?php if (!empty($message)): ?><p><?= e($message) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('auth/password/forgot')) ?>"><?= safe_form() ?>
<label>Email <input type="email" name="email" required autocomplete="email"></label>
<button type="submit">Send reset link</button></form></main></body></html>
