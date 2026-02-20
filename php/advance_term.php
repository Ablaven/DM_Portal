<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_term_helpers.php';
require_once __DIR__ . '/_week_schema_helpers.php';

function bad_request(string $message): void
{
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

try {
    auth_require_api_access();
    auth_require_roles(['admin']);

    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $advanceMode = trim((string)($_POST['advance_mode'] ?? 'auto'));

    $pdo = get_pdo();
    dmportal_ensure_terms_table($pdo);

    $activeTermId = dmportal_get_active_term_id($pdo);
    $stmt = $pdo->prepare('SELECT term_id, semester, academic_year_id FROM terms WHERE term_id = :term_id');
    $stmt->execute([':term_id' => $activeTermId]);
    $term = $stmt->fetch();
    if (!$term) {
        bad_request('Active term not found.');
    }

    $currentSemester = (int)($term['semester'] ?? 0);
    $academicYearId = (int)($term['academic_year_id'] ?? 0);
    if ($currentSemester <= 0 || $academicYearId <= 0) {
        bad_request('Invalid active term metadata.');
    }

    if ($currentSemester === 1) {
        $nextTermId = dmportal_get_or_create_term($pdo, $academicYearId, 2, 'Semester 2');
        dmportal_set_active_term($pdo, $nextTermId);
        $weekId = dmportal_reset_weeks_for_term($pdo, $nextTermId, $startDate !== '' ? $startDate : null, false);

        echo json_encode([
            'success' => true,
            'data' => [
                'action' => 'advance_semester',
                'semester' => 2,
                'academic_year_id' => $academicYearId,
                'term_id' => $nextTermId,
                'week_id' => $weekId,
            ],
        ]);
        return;
    }

    if ($currentSemester !== 2) {
        bad_request('Only semesters 1 and 2 are supported.');
    }

    $studentActions = $_POST['student_actions'] ?? null;
    if (is_string($studentActions) && $studentActions !== '') {
        $decoded = json_decode($studentActions, true);
        if (is_array($decoded)) {
            $studentActions = $decoded;
        }
    }

    if ($advanceMode !== 'auto' && $advanceMode !== 'custom') {
        $advanceMode = 'auto';
    }

    if ($advanceMode === 'custom' && !is_array($studentActions)) {
        bad_request('Custom advance mode requires student_actions payload.');
    }

    $pdo->beginTransaction();
    if ($advanceMode === 'custom') {
        dmportal_apply_student_actions($pdo, $studentActions);
    } else {
        dmportal_auto_advance_students($pdo);
    }

    dmportal_close_academic_year($pdo, $academicYearId);
    $newYearId = dmportal_create_next_academic_year($pdo, $academicYearId);
    $term1Id = dmportal_get_or_create_term($pdo, $newYearId, 1, 'Semester 1');
    $term2Id = dmportal_get_or_create_term($pdo, $newYearId, 2, 'Semester 2', false);
    dmportal_set_active_term($pdo, $term1Id);
    $weekId = dmportal_reset_weeks_for_term($pdo, $term1Id, $startDate !== '' ? $startDate : null, false);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'action' => 'advance_year',
            'academic_year_id' => $newYearId,
            'term_id' => $term1Id,
            'week_id' => $weekId,
            'student_mode' => $advanceMode,
            'term2_id' => $term2Id,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to advance semester/year.']);
}
