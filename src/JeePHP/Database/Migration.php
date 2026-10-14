<?php
declare(strict_types=1);

namespace JeePHP\Database;

use PDO;

interface Migration
{
    public function up(PDO $database): void;

    public function down(PDO $database): void;
}
