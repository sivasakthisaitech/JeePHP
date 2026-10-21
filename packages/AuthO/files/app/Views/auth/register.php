<!doctype html>
<html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create account</title>
<body><main><h1>Create account</h1><?php if (isset($error)): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e(url('auth/register')) ?>"><?= safe_form() ?>
<label>Name <input name="name" autocomplete="name" required maxlength="150"></label>
<label>Email <input type="email" name="email" autocomplete="email" required maxlength="190"></label>
<label>Password <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<button type="submit">Create account</button></form><p><a href="<?= e(url('auth/login')) ?>">Sign in</a></p></main></body></html>
