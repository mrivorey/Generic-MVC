<?php

namespace App\Core;

class Paginator
{
    private static ?int $currentPage = null;

    public static function paginate(string $table, int $perPage = 15, array $conditions = [], ?string $orderBy = null): PaginationResult
    {
        $page = self::resolveCurrentPage();
        $pdo = Database::getConnection();

        // Build WHERE clause
        $where = '';
        $bindings = [];
        if (!empty($conditions)) {
            $parts = [];
            foreach ($conditions as $column => $value) {
                if (is_array($value)) {
                    // Operator condition: ['column' => ['>=', 5]]
                    $parts[] = "`{$column}` {$value[0]} ?";
                    $bindings[] = $value[1];
                } else {
                    $parts[] = "`{$column}` = ?";
                    $bindings[] = $value;
                }
            }
            $where = ' WHERE ' . implode(' AND ', $parts);
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM `{$table}`{$where}";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($bindings);
        $total = (int) $stmt->fetchColumn();

        // Calculate pagination
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // Fetch results
        $selectSql = "SELECT * FROM `{$table}`{$where}";
        if ($orderBy) {
            $selectSql .= " ORDER BY {$orderBy}";
        }
        $selectSql .= " LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($selectSql);
        $stmt->execute($bindings);
        $items = $stmt->fetchAll();

        return new PaginationResult($items, $page, $lastPage, $total, $perPage);
    }

    public static function fromQuery(string $countSql, string $selectSql, array $bindings = [], int $perPage = 15): PaginationResult
    {
        $page = self::resolveCurrentPage();
        $pdo = Database::getConnection();

        // Count total
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($bindings);
        $total = (int) $stmt->fetchColumn();

        // Calculate pagination
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        // Fetch results
        $fullSelect = $selectSql . " LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($fullSelect);
        $stmt->execute($bindings);
        $items = $stmt->fetchAll();

        return new PaginationResult($items, $page, $lastPage, $total, $perPage);
    }

    public static function reset(): void
    {
        self::$currentPage = null;
    }

    public static function setCurrentPage(int $page): void
    {
        self::$currentPage = $page;
    }

    private static function resolveCurrentPage(): int
    {
        if (self::$currentPage !== null) {
            return self::$currentPage;
        }
        return max(1, (int) ($_GET['page'] ?? 1));
    }
}
