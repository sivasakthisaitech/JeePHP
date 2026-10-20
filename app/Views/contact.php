<h1><?= e($title ?? 'Contact JeePHP') ?></h1>
<form method="post" action="save">
    <?= safe_form() ?>
    <label>Name <input name="name" required></label>
    <button type="submit">Send</button>
</form>
