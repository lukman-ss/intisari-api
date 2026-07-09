<?php

return function (\PDO $pdo) {
    $sql = <<<SQL
        ALTER TABLE posts ADD COLUMN deleted_at TEXT DEFAULT NULL;
    SQL;

    $pdo->exec($sql);
};
