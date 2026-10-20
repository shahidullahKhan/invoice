<?php
namespace InvoiceTool;

use PDO;

/** Thin PDO wrapper; auto-creates the temporary SQLite schema on first use. */
class Db
{
    /** @var PDO */
    private $pdo;

    public function __construct(array $config)
    {
        $this->pdo = new PDO($config['db_dsn'], $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if (strpos($config['db_dsn'], 'sqlite:') === 0) {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec(file_get_contents(__DIR__ . '/../schema.sqlite.sql'));
        }
    }

    /** Run a statement and return it (for fetch/rowCount). */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /** Execute $fn inside a transaction. */
    public function transaction(callable $fn)
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
