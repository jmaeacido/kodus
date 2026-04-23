<?php

if (!function_exists('db_stmt_fetch_all_assoc')) {
    function db_stmt_fetch_all_assoc(mysqli_stmt $stmt): array
    {
        if (method_exists($stmt, 'get_result')) {
            $result = $stmt->get_result();
            if ($result instanceof mysqli_result) {
                return $result->fetch_all(MYSQLI_ASSOC);
            }
        }

        $metadata = $stmt->result_metadata();
        if (!$metadata) {
            return [];
        }

        $row = [];
        $bindValues = [];

        while ($field = $metadata->fetch_field()) {
            $row[$field->name] = null;
            $bindValues[] = &$row[$field->name];
        }

        $metadata->free();

        if ($bindValues === []) {
            return [];
        }

        call_user_func_array([$stmt, 'bind_result'], $bindValues);

        $rows = [];
        while ($stmt->fetch()) {
            $currentRow = [];
            foreach ($row as $name => $value) {
                $currentRow[$name] = $value;
            }
            $rows[] = $currentRow;
        }

        return $rows;
    }
}

if (!function_exists('db_stmt_fetch_one_assoc')) {
    function db_stmt_fetch_one_assoc(mysqli_stmt $stmt): ?array
    {
        $rows = db_stmt_fetch_all_assoc($stmt);
        return $rows[0] ?? null;
    }
}
