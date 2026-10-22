<?php
declare(strict_types=1);

namespace JeePHP\Packages;

use RuntimeException;

final class CrudGenerator
{
    public function __construct(private readonly string $root) {}

    public function generate(string $name, array $fields): array
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new RuntimeException('CRUD name must be a PHP class name, such as Product.');
        }
        if ($fields === [] || count($fields) > 20) {
            throw new RuntimeException('Pass 1 to 20 field names, for example name,price,description.');
        }
        foreach ($fields as $field) {
            if (!is_string($field) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field) !== 1 || $field === 'id') {
                throw new RuntimeException('Fields must use lowercase names such as name or unit_price; id is generated automatically.');
            }
        }
        if (count(array_unique($fields)) !== count($fields)) {
            throw new RuntimeException('Field names must be unique.');
        }

        $class = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        $class = str_ends_with($class, 'Controller') ? substr($class, 0, -10) : $class;
        $model = $class;
        $controller = $model . 'Controller';
        $table = self::snakePlural($model);
        $viewDirectory = $this->root . '/app/Views/' . $table;
        $modelPath = $this->root . '/app/Models/' . $model . '.php';
        $controllerPath = $this->root . '/app/Controllers/' . $controller . '.php';
        $migrationPath = $this->root . '/database/migrations/' . date('Ymd_His') . '_create_' . $table . '_table.php';
        $indexPath = $viewDirectory . '/index.php';
        $formPath = $viewDirectory . '/form.php';
        $showPath = $viewDirectory . '/show.php';
        $routesPath = $this->root . '/routes/web.php';
        $marker = '// JeePHP CRUD: ' . $table;

        $this->ensureNotExists([$modelPath, $controllerPath, $migrationPath, $indexPath, $formPath, $showPath]);
        $modelFields = var_export(array_values($fields), true);
        $fieldColumns = implode(', ', array_map(static fn (string $field): string => "'" . $field . "'", $fields));
        $modelCode = "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Models;\n\nuse JeePHP\\Database\\Model;\n\nfinal class {$model} extends Model\n{\n    protected static string \$table = '{$table}';\n    protected array \$fillable = {$modelFields};\n}\n";

        $rules = [];
        foreach ($fields as $field) {
            $rules[] = "            '{$field}' => 'required|max:255',";
        }
        $rulesCode = implode("\n", $rules);
        $only = implode(', ', array_map(static fn (string $field): string => "'" . $field . "'", $fields));
        $controllerCode = <<<PHP
<?php
declare(strict_types=1);

namespace App\\Controllers;

use App\\Models\\{$model};
use JeePHP\\Http\\Request;

final class {$controller}
{
    public function index(Request \$request): void
    {
        \$items = {$model}::query()->orderBy('id', 'DESC')->limit(200)->get();
        load_view('{$table}/index', ['items' => \$items]);
    }

    public function create(Request \$request): void
    {
        load_view('{$table}/form', ['item' => [], 'errors' => [], 'action' => '{$table}', 'mode' => 'create']);
    }

    public function store(Request \$request): void
    {
        \$values = \$request->only({$only});
        \$errors = validate(\$values, [
{$rulesCode}
        ]);
        if (\$errors !== []) {
            http_response_code(422);
            load_view('{$table}/form', ['item' => \$values, 'errors' => \$errors, 'action' => '{$table}', 'mode' => 'create']);
            return;
        }
        {$model}::create(\$values);
        redirect('{$table}');
    }

    public function show(Request \$request): void
    {
        \$item = {$model}::find((int) \$request->route('id'));
        if (\$item === null) { http_response_code(404); echo 'Record not found'; return; }
        load_view('{$table}/show', ['item' => \$item]);
    }

    public function edit(Request \$request): void
    {
        \$item = {$model}::find((int) \$request->route('id'));
        if (\$item === null) { http_response_code(404); echo 'Record not found'; return; }
        load_view('{$table}/form', ['item' => \$item, 'errors' => [], 'action' => '{$table}/' . \$item->id . '/update', 'mode' => 'edit']);
    }

    public function update(Request \$request): void
    {
        \$item = {$model}::find((int) \$request->route('id'));
        if (\$item === null) { http_response_code(404); echo 'Record not found'; return; }
        \$values = \$request->only({$only});
        \$errors = validate(\$values, [
{$rulesCode}
        ]);
        if (\$errors !== []) {
            http_response_code(422);
            load_view('{$table}/form', ['item' => array_merge(\$item->toArray(), \$values), 'errors' => \$errors, 'action' => '{$table}/' . \$item->id . '/update', 'mode' => 'edit']);
            return;
        }
        \$item->update(\$values);
        redirect('{$table}');
    }

    public function destroy(Request \$request): void
    {
        \$item = {$model}::find((int) \$request->route('id'));
        if (\$item === null) { http_response_code(404); echo 'Record not found'; return; }
        \$item->delete();
        redirect('{$table}');
    }
}
PHP;

        $migration = <<<PHP
<?php
declare(strict_types=1);

use JeePHP\\Database\\Migration;

return new class implements Migration {
    public function up(\\PDO \$database): void
    {
        create_table('{$table}')
            ->column({$fieldColumns})
            ->timestamps()
            ->create();
    }

    public function down(\\PDO \$database): void
    {
        drop_table('{$table}');
    }
};
PHP;

        $indexRows = [];
        foreach ($fields as $field) {
            $indexRows[] = '                <td><?= e($item->' . $field . ' ?? \'\') ?></td>';
        }
        $indexRowCode = implode("\n", $indexRows);
        $labels = array_map(static fn (string $field): string => ucwords(str_replace('_', ' ', $field)), $fields);
        $indexHeaders = implode('', array_map(static fn (string $label): string => '<th>' . $label . '</th>', $labels));
        $formInputs = [];
        foreach ($fields as $index => $field) {
            $label = $labels[$index];
            $formInputs[] = '    <label>' . $label . '<input name="' . $field . '" value="<?= e($value(\'' . $field . '\')) ?>" required></label>';
        }
        $formInputCode = implode("\n", $formInputs);
        $indexView = <<<PHP
<h1>{$model} records</h1>
<p><a href="<?= e(url('{$table}/create')) ?>">Add {$model}</a></p>
<table>
    <thead><tr><th>ID</th>{$indexHeaders}<th>Actions</th></tr></thead>
    <tbody>
    <?php foreach (\$items as \$item): ?>
        <tr>
            <td><?= e(\$item->id) ?></td>
{$indexRowCode}
            <td>
                <a href="<?= e(url('{$table}/' . \$item->id)) ?>">View</a>
                <a href="<?= e(url('{$table}/' . \$item->id . '/edit')) ?>">Edit</a>
                <form method="post" action="<?= e(url('{$table}/' . \$item->id . '/delete')) ?>">
                    <?= safe_form() ?><button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
PHP;
        $formView = <<<PHP
<?php
\$value = static function (string \$key) use (\$item): string {
    \$value = is_array(\$item) ? (\$item[\$key] ?? '') : (is_object(\$item) ? (\$item->{\$key} ?? '') : '');
    return is_scalar(\$value) ? (string) \$value : '';
};
?>
<h1><?= \$mode === 'edit' ? 'Edit {$model}' : 'Create {$model}' ?></h1>
<?php foreach (\$errors as \$messages): foreach (\$messages as \$message): ?>
    <p><?= e(\$message) ?></p>
<?php endforeach; endforeach; ?>
<form method="post" action="<?= e(url(\$action)) ?>">
    <?= safe_form() ?>
{$formInputCode}
    <button type="submit">Save</button>
</form>
PHP;
        $showItems = [];
        foreach ($fields as $index => $field) {
            $showItems[] = '<p><strong>' . $labels[$index] . ':</strong> <?= e($item->' . $field . ' ?? \'\') ?></p>';
        }
        $showView = '<h1>' . $model . ' #<?= e($item->id) ?></h1>' . "\n" . implode("\n", $showItems)
            . "\n<a href=\"<?= e(url('{$table}')) ?>\">Back to list</a>\n";

        $routes = $marker . "\n"
            . "route('/{$table}', '{$controller}@index');\n"
            . "route('/{$table}/create', '{$controller}@create');\n"
            . "route('POST /{$table}', '{$controller}@store');\n"
            . "route('/{$table}/:id/edit', '{$controller}@edit');\n"
            . "route('POST /{$table}/:id/update', '{$controller}@update');\n"
            . "route('POST /{$table}/:id/delete', '{$controller}@destroy');\n"
            . "route('/{$table}/:id', '{$controller}@show');\n";
        $existingRoutes = is_file($routesPath) ? (string) file_get_contents($routesPath) : "<?php\ndeclare(strict_types=1);\n\n";
        if (str_contains($existingRoutes, $marker)) {
            throw new RuntimeException('CRUD routes already exist for ' . $table . '.');
        }

        $files = [
            $modelPath => $modelCode,
            $controllerPath => $controllerCode,
            $migrationPath => $migration,
            $indexPath => $indexView,
            $formPath => $formView,
            $showPath => $showView,
        ];
        $created = [];
        $routesExisted = is_file($routesPath);
        try {
            foreach ($files as $path => $contents) {
                $directory = dirname($path);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('Could not create folder: ' . $directory);
                }
                if (file_put_contents($path, $contents . (str_ends_with($contents, "\n") ? '' : "\n"), LOCK_EX) === false) {
                    throw new RuntimeException('Could not write generated file: ' . $path);
                }
                $created[] = $path;
            }
            if (file_put_contents($routesPath, rtrim($existingRoutes) . "\n\n" . $routes, LOCK_EX) === false) {
                throw new RuntimeException('Could not update routes/web.php.');
            }
        } catch (\Throwable $error) {
            foreach (array_reverse($created) as $path) {
                @unlink($path);
            }
            if ($routesExisted) {
                file_put_contents($routesPath, $existingRoutes, LOCK_EX);
            } else {
                @unlink($routesPath);
            }
            throw $error;
        }
        return array_map(static fn (string $path): string => substr($path, strlen($this->root) + 1), array_merge(array_keys($files), [$routesPath]));
    }

    private function ensureNotExists(array $paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                throw new RuntimeException('CRUD generation stopped because this file already exists: ' . substr($path, strlen($this->root) + 1));
            }
        }
    }

    private static function snakePlural(string $class): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
        return str_ends_with($snake, 's') ? $snake : $snake . 's';
    }
}
