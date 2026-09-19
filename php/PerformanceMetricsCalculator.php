<?php

declare(strict_types=1);

/**
 * PerformanceMetricsCalculator
 * 
 * Calculates performance metrics from grade data for the student dashboard.
 * This class contains the core logic for computing averages, highest/lowest grades,
 * and counting high-performing and low-performing courses.
 * 
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7
 */
class PerformanceMetricsCalculator
{
    /**
     * Calculate performance metrics from an array of grade records
     * 
     * @param array $grades Array of grade records with 'has_grade' and 'final_score' keys
     * @return array Performance metrics including average, highest, lowest, and counts
     */
    public static function calculate(array $grades): array
    {
        // Extract all final scores (non-null values only)
        $gradedScores = [];
        foreach ($grades as $grade) {
            if (isset($grade['has_grade']) && $grade['has_grade'] === true && isset($grade['final_score'])) {
                $gradedScores[] = (float)$grade['final_score'];
            }
        }
        
        $totalCourses = count($grades);
        $totalGradedCourses = count($gradedScores);
        
        // Initialize performance metrics
        $averageGrade = null;
        $highestGrade = null;
        $lowestGrade = null;
        $highPerformingCount = 0;
        $lowPerformingCount = 0;
        
        // Calculate metrics only if there are graded courses
        // Requirement 5.1: Compute average grade across all courses with recorded grades
        if ($totalGradedCourses > 0) {
            // Calculate average grade
            $averageGrade = round(array_sum($gradedScores) / $totalGradedCourses, 1);
            
            // Requirement 5.7: When fewer than two courses have grades, do not compute highest and lowest
            // Calculate highest and lowest only if 2 or more graded courses
            if ($totalGradedCourses >= 2) {
                // Requirement 5.2: Identify the highest grade from all courses
                $highestGrade = round(max($gradedScores), 1);
                
                // Requirement 5.3: Identify the lowest grade from all courses
                $lowestGrade = round(min($gradedScores), 1);
            }
            
            // Requirement 5.4: Count the number of courses with grades above 85
            foreach ($gradedScores as $score) {
                if ($score > 85) {
                    $highPerformingCount++;
                }
            }
            
            // Requirement 5.5: Count the number of courses with grades below 60
            foreach ($gradedScores as $score) {
                if ($score < 60) {
                    $lowPerformingCount++;
                }
            }
        }
        
        // Requirement 5.6: Display average grade, highest grade, lowest grade, 
        // high-performing course count, and low-performing course count
        return [
            'average_grade' => $averageGrade,
            'highest_grade' => $highestGrade,
            'lowest_grade' => $lowestGrade,
            'high_performing_count' => $highPerformingCount,
            'low_performing_count' => $lowPerformingCount,
            'total_graded_courses' => $totalGradedCourses,
            'total_courses' => $totalCourses
        ];
    }
}
