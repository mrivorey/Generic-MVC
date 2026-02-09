<?php

namespace App\Core;

abstract class Model
{
    protected static string $table;
    protected static array $fillable = [];

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $table = static::$table;
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        $pdo = Database::getConnection();
        $table = static::$table;
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
        $stmt->execute([$value]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function where(string $column, mixed $value): array
    {
        $pdo = Database::getConnection();
        $table = static::$table;
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$column}` = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public static function all(?string $orderBy = null): array
    {
        $pdo = Database::getConnection();
        $table = static::$table;
        $sql = "SELECT * FROM `{$table}`";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $data = self::filterFillable($data);
        $pdo = Database::getConnection();
        $table = static::$table;
        $columns = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $pdo->prepare("INSERT INTO `{$table}` (`{$columns}`) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $data = self::filterFillable($data);
        if (empty($data)) {
            return false;
        }
        $pdo = Database::getConnection();
        $table = static::$table;
        $sets = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $stmt = $pdo->prepare("UPDATE `{$table}` SET {$sets} WHERE id = ?");
        $values = array_values($data);
        $values[] = $id;
        return $stmt->execute($values);
    }

    public static function delete(int $id): bool
    {
        $pdo = Database::getConnection();
        $table = static::$table;
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function query(string $sql, array $bindings = []): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    protected static function filterFillable(array $data): array
    {
        if (empty(static::$fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip(static::$fillable));
    }
}
