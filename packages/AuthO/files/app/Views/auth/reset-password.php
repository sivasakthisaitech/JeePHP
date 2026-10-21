<!doctype html>
<html lang="en"><meta charset="utf-8"><title>Choose a new password</title>
<body><main><h1>Choose a new password</h1><?php if (!empty($error)): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('auth/password/reset/' . $token)) ?>"><?= safe_form() ?>
<label>New password <input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
<label>Repeat password <input type="password" name="password_confirmation" minlength="12" maxlength="72" required autocomplete="new-password"></label>
<button type="submit">Save password</button></form></main></body></html>
