<?php

declare(strict_types=1);

/**
 * Helpers for the (new) students schema.
 * Expected columns:
 * - student_id (PK)
 * - full_name, email
 * - student_code (unique)
 * - program
 * - year_level
 * - semester (0 = applies to all semesters)
 */

function normalize_program(string $p): string {
    $p = trim($p);
    return $p !== '' ? $p : 'Digital Marketing';
}

function normalize_year_level(int $y): int {
    return ($y >= 1 && $y <= 3) ? $y : 0;
}
