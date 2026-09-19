<?php

declare(strict_types=1);

/**
 * Grade Aggregation Helpers for Student Dashboard
 * 
 * This module provides testable functions for grade data processing.
 * Extracted from get_student_dashboard.php for unit testing.
 */

/**
 * Format grades data with "N/A" for missing grades
 * 
 * Validates: Requirements 3.1, 3.2, 3.3, 3.6, 3.8
 * 
 * @param array $gradesRows Raw database rows from grades query
 * @return array Formatted grades with has_grade flags and display values
 */
function dmportal_format_grades_data(array $gradesRows): array
{
    $grades = [];
    
    foreach ($gradesRows as $row) {
        $finalScore = $row['final_score'];
        $hasGrade = $finalScore !== null;
        
        $grades[] = [
            'course_id' => (int)$row['course_id'],
            'course_name' => $row['course_name'],
            'course_type' => $row['course_type'],
            'subject_code' => $row['subject_code'],
            'final_score' => $hasGrade ? (float)$finalScore : null,
            'final_score_display' => $hasGrade ? number_format((float)$finalScore, 2) : 'N/A',
            'has_grade' => $hasGrade
        ];
    }
    
    return $grades;
}

/**
 * Filter courses by year level, program, and semester
 * 
 * Validates: Requirements 3.1, 3.2, 3.3
 * 
 * This function demonstrates the filtering logic used in the SQL query.
 * In production, filtering happens in SQL, but this helper shows the logic.
 * 
 * @param array $courses All courses
 * @param int $yearLevel Student's year level
 * @param string $program Student's program
 * @param int $semester Target semester
 * @return array Filtered courses
 */
function dmportal_filter_courses_by_student(
    array $courses,
    int $yearLevel,
    string $program,
    int $semester
): array {
    return array_filter($courses, function($course) use ($yearLevel, $program, $semester) {
        return $course['year_level'] === $yearLevel
            && $course['program'] === $program
            && $course['semester'] === $semester;
    });
}

/**
 * Check if a course has a valid assignment
 * 
 * Validates: Requirement 3.4
 * 
 * @param mixed $doctorId Course's doctor_id from database
 * @return bool True if course has valid assignment
 */
function dmportal_course_has_valid_assignment($doctorId): bool
{
    // Course should have doctor_id NOT NULL or doctor_id = 0 (admin config)
    return $doctorId !== null || $doctorId === 0;
}

/**
 * Count graded vs total courses
 * 
 * Validates: Requirement 3.7
 * 
 * @param array $grades Formatted grades array
 * @return array ['graded' => int, 'total' => int]
 */
function dmportal_count_graded_courses(array $grades): array
{
    $total = count($grades);
    $graded = count(array_filter($grades, fn($g) => $g['has_grade']));
    
    return [
        'graded' => $graded,
        'total' => $total
    ];
}
