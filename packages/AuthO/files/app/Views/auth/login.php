<!doctype html>
<html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in</title>
<body><main><h1>Sign in</h1><?php if (isset($error)): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('auth/login')) ?>"><?= safe_form() ?>
<label>Email <input type="email" name="email" autocomplete="username" required maxlength="190"></label>
<label>Password <input type="password" name="password" autocomplete="current-password" required></label>
<button type="submit">Sign in</button></form><p><a href="<?= e(url('auth/register')) ?>">Create account</a></p></main></body></html>
