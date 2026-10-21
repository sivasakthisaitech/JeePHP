<!doctype html>
<html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Roles and permissions</title>
<body><main><h1>Roles and permissions</h1><?php if (isset($notice) && $notice): ?><p><?= e($notice) ?></p><?php endif; ?>
<section><h2>Create role</h2><form method="post" action="<?= e(url('roles/create')) ?>"><?= safe_form() ?><label>Role name <input name="name" placeholder="editor" required maxlength="64"></label><button>Create role</button></form></section>
<section><h2>Create permission</h2><form method="post" action="<?= e(url('roles/permissions/create')) ?>"><?= safe_form() ?><label>Permission name <input name="name" placeholder="posts.publish" required maxlength="96"></label><button>Create permission</button></form></section>
<section><h2>Grant permission</h2><form method="post" action="<?= e(url('roles/grant')) ?>"><?= safe_form() ?><label>Role <select name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= e($role['id']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></label><label>Permission <select name="permission_id" required><?php foreach ($permissions as $permission): ?><option value="<?= e($permission['id']) ?>"><?= e($permission['name']) ?></option><?php endforeach; ?></select></label><button>Grant</button></form></section>
<h2>Current roles</h2><ul><?php foreach ($roles as $role): ?><li><?= e($role['name']) ?></li><?php endforeach; ?></ul>
<h2>Current permissions</h2><ul><?php foreach ($permissions as $permission): ?><li><?= e($permission['name']) ?></li><?php endforeach; ?></ul>
</main></body></html>
