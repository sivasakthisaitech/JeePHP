<!doctype html>
<html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account</title>
<body><main><h1>Welcome, <?= e($name) ?></h1><form method="post" action="<?= e(url('auth/logout')) ?>"><?= safe_form() ?><button type="submit">Sign out</button></form></main></body></html>
