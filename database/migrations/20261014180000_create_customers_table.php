<?php
declare(strict_types=1);

use JeePHP\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        create_table('customers')
            ->stringColumn('name', 150, false)
            ->stringColumn('mobile', 30)
            ->stringColumn('email', 190)
            ->boolean('status', 1)
            ->timestamps()
            ->create();
    }

    public function down(\PDO $database): void
    {
        drop_table('customers');
    }
};
