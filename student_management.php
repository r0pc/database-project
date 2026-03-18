<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_login();

$pdo = db();
$user = current_user();
$assessors = fetch_assessors();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_student') {
        $studentId = strtoupper(trim($_POST['student_id'] ?? ''));
        $studentName = trim($_POST['student_name'] ?? '');
        $programme = trim($_POST['programme'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $assessorId = (int) ($_POST['assessor_id'] ?? 0);
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');
        $notes = trim($_POST['notes'] ?? '');
        $editingId = trim($_POST['editing_id'] ?? '');

        if ($studentId === '' || $studentName === '' || $programme === '' || $companyName === '' || $assessorId === 0 || $startDate === '' || $endDate === '') {
            flash('error', 'All required student and internship fields must be completed.');
            redirect('student_management.php');
        }

        if (!preg_match('/^[A-Z0-9-]{4,20}$/', $studentId)) {
            flash('error', 'Student ID must use 4 to 20 letters, numbers, or hyphens.');
            redirect('student_management.php');
        }

        if ($editingId !== '' && $editingId !== $studentId) {
            flash('error', 'Student ID cannot be changed during edit.');
            redirect('student_management.php');
        }

        $pdo->beginTransaction();
        try {
            $studentExists = $pdo->prepare('SELECT student_id FROM students WHERE student_id = :student_id');
            $studentExists->execute(['student_id' => $studentId]);
            $exists = (bool) $studentExists->fetch();

            if ($exists) {
                $stmt = $pdo->prepare('UPDATE students SET student_name = :student_name, programme = :programme, company_name = :company_name WHERE student_id = :student_id');
                $stmt->execute([
                    'student_name' => $studentName,
                    'programme' => $programme,
                    'company_name' => $companyName,
                    'student_id' => $studentId,
                ]);

                $internshipExists = $pdo->prepare('SELECT internship_id FROM internships WHERE student_id = :student_id');
                $internshipExists->execute(['student_id' => $studentId]);
                if ($internshipExists->fetch()) {
                    $stmt = $pdo->prepare('UPDATE internships SET assessor_id = :assessor_id, start_date = :start_date, end_date = :end_date, status = :status, notes = :notes WHERE student_id = :student_id');
                    $stmt->execute([
                        'assessor_id' => $assessorId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => $status,
                        'notes' => $notes ?: null,
                        'student_id' => $studentId,
                    ]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO internships (student_id, assessor_id, start_date, end_date, status, notes) VALUES (:student_id, :assessor_id, :start_date, :end_date, :status, :notes)');
                    $stmt->execute([
                        'student_id' => $studentId,
                        'assessor_id' => $assessorId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => $status,
                        'notes' => $notes ?: null,
                    ]);
                }

                $pdo->commit();
                flash('success', 'Student and internship record updated.');
                redirect('student_management.php');
            }

            $stmt = $pdo->prepare('INSERT INTO students (student_id, student_name, programme, company_name) VALUES (:student_id, :student_name, :programme, :company_name)');
            $stmt->execute([
                'student_id' => $studentId,
                'student_name' => $studentName,
                'programme' => $programme,
                'company_name' => $companyName,
            ]);

            $stmt = $pdo->prepare('INSERT INTO internships (student_id, assessor_id, start_date, end_date, status, notes) VALUES (:student_id, :assessor_id, :start_date, :end_date, :status, :notes)');
            $stmt->execute([
                'student_id' => $studentId,
                'assessor_id' => $assessorId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'notes' => $notes ?: null,
            ]);

            $pdo->commit();
            flash('success', 'Student and internship record created.');
            redirect('student_management.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            log_app_error('Student save failed', $e);
            flash('error', 'Unable to save the student record. Please review the input and try again.');
            redirect('student_management.php');
        }
    }

    if ($action === 'delete_student') {
        $studentId = trim($_POST['student_id'] ?? '');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE a FROM assessments a JOIN internships i ON i.internship_id = a.internship_id WHERE i.student_id = :student_id');
            $stmt->execute(['student_id' => $studentId]);
            $stmt = $pdo->prepare('DELETE FROM internships WHERE student_id = :student_id');
            $stmt->execute(['student_id' => $studentId]);
            $stmt = $pdo->prepare('DELETE FROM students WHERE student_id = :student_id');
            $stmt->execute(['student_id' => $studentId]);
            $pdo->commit();
            flash('success', 'Student record deleted.');
            redirect('student_management.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            log_app_error('Student delete failed', $e);
            flash('error', 'Unable to delete student record.');
            redirect('student_management.php');
        }
    }
}

$editRecord = null;
if (is_admin() && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT s.student_id, s.student_name, s.programme, s.company_name, i.assessor_id, i.start_date, i.end_date, i.status, i.notes
        FROM students s
        JOIN internships i ON i.student_id = s.student_id
        WHERE s.student_id = :student_id');
    $stmt->execute(['student_id' => $_GET['edit']]);
    $editRecord = $stmt->fetch() ?: null;
}

$search = trim($_GET['search'] ?? '');
$programmeFilter = trim($_GET['programme'] ?? '');
$statusFilter = trim($_GET['assessment_status'] ?? 'all');

$conditions = [];
$params = [];

if (is_assessor()) {
    $conditions[] = 'i.assessor_id = :assessor_id';
    $params['assessor_id'] = $user['user_id'];
}

if ($search !== '') {
    $conditions[] = '(s.student_id LIKE :search OR s.student_name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($programmeFilter !== '') {
    $conditions[] = 's.programme = :programme';
    $params['programme'] = $programmeFilter;
}

if ($statusFilter === 'assessed') {
    $conditions[] = 'a.assessment_id IS NOT NULL';
} elseif ($statusFilter === 'pending') {
    $conditions[] = 'a.assessment_id IS NULL';
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$sql = 'SELECT s.student_id, s.student_name, s.programme, s.company_name, i.internship_id, i.start_date, i.end_date, i.status,
        i.notes, u.full_name AS assessor_name, u.user_id AS assessor_id, a.assessment_id, a.final_mark, a.assessed_at
    FROM students s
    JOIN internships i ON i.student_id = s.student_id
    JOIN users u ON u.user_id = i.assessor_id
    LEFT JOIN assessments a ON a.internship_id = i.internship_id' . $where . '
    ORDER BY s.student_id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$programmeStmt = $pdo->query('SELECT DISTINCT programme FROM students ORDER BY programme');
$programmes = array_column($programmeStmt->fetchAll(), 'programme');
$stats = fetch_dashboard_stats(is_assessor() ? (int) $user['user_id'] : null);

render_header('Student and Internship Management');
?>
<section class="toolbar">
    <div>
        <strong><?= is_admin() ? 'Admin workspace' : 'Assessor workspace' ?></strong><br />
        <span class="helper">
            <?= is_admin()
                ? 'Create students, assign internships, and link them to assessor accounts.'
                : 'View the students assigned to you and access assessment entry quickly.' ?>
        </span>
    </div>
    <div class="toolbar-actions">
        <a class="button-link secondary" href="result_entry.php">Open Assessment Entry</a>
        <?php if (is_admin()): ?>
            <a class="button-link ghost" href="user_management.php">Manage Assessor Accounts</a>
        <?php endif; ?>
        <a class="button-link ghost" href="results.php">View Results</a>
    </div>
</section>

<section class="stats">
    <article class="stat-card">
        <span>Total Students</span>
        <strong><?= h((string) $stats['total_students']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Students with Assessment</span>
        <strong><?= h((string) $stats['assessed_count']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Average Final Mark</span>
        <strong><?= h($stats['average_mark']) ?></strong>
    </article>
    <article class="stat-card">
        <span>Highest Final Mark</span>
        <strong><?= h($stats['highest_mark']) ?></strong>
    </article>
</section>

<section class="content-grid">
    <?php if (is_admin()): ?>
        <article class="panel">
            <h2><?= $editRecord ? 'Edit Student and Internship' : 'Add Student and Internship' ?></h2>
            <p class="helper">This form satisfies both student management and internship management requirements.</p>
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="save_student" />
                <input type="hidden" name="editing_id" value="<?= h($editRecord['student_id'] ?? '') ?>" />
                <div>
                    <label for="student_id">Student ID</label>
                    <input id="student_id" name="student_id" value="<?= h($editRecord['student_id'] ?? '') ?>" required />
                </div>
                <div>
                    <label for="student_name">Student Name</label>
                    <input id="student_name" name="student_name" value="<?= h($editRecord['student_name'] ?? '') ?>" required />
                </div>
                <div>
                    <label for="programme">Programme</label>
                    <input id="programme" name="programme" value="<?= h($editRecord['programme'] ?? '') ?>" required />
                </div>
                <div>
                    <label for="company_name">Internship Company</label>
                    <input id="company_name" name="company_name" value="<?= h($editRecord['company_name'] ?? '') ?>" required />
                </div>
                <div>
                    <label for="assessor_id">Assigned Assessor</label>
                    <select id="assessor_id" name="assessor_id" required>
                        <option value="">Select assessor</option>
                        <?php foreach ($assessors as $assessor): ?>
                            <option value="<?= (int) $assessor['user_id'] ?>" <?= ((int) ($editRecord['assessor_id'] ?? 0) === (int) $assessor['user_id']) ? 'selected' : '' ?>>
                                <?= h($assessor['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid two">
                    <div>
                        <label for="start_date">Start Date</label>
                        <input id="start_date" name="start_date" type="date" value="<?= h($editRecord['start_date'] ?? '') ?>" required />
                    </div>
                    <div>
                        <label for="end_date">End Date</label>
                        <input id="end_date" name="end_date" type="date" value="<?= h($editRecord['end_date'] ?? '') ?>" required />
                    </div>
                </div>
                <div>
                    <label for="status">Internship Status</label>
                    <select id="status" name="status">
                        <?php $selectedStatus = $editRecord['status'] ?? 'Active'; ?>
                        <option value="Active" <?= $selectedStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="Completed" <?= $selectedStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div>
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes"><?= h($editRecord['notes'] ?? '') ?></textarea>
                </div>
                <div class="button-row">
                    <button type="submit"><?= $editRecord ? 'Update Record' : 'Create Record' ?></button>
                    <a class="button-link ghost" href="student_management.php">Clear</a>
                </div>
            </form>
        </article>
    <?php endif; ?>

    <article class="panel">
        <h2><?= is_admin() ? 'Student Directory' : 'Assigned Students' ?></h2>
        <form method="get" class="search-row" style="margin-bottom: 16px;">
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by student ID or student name" />
            <select name="programme">
                <option value="">All Programmes</option>
                <?php foreach ($programmes as $programme): ?>
                    <option value="<?= h($programme) ?>" <?= $programmeFilter === $programme ? 'selected' : '' ?>><?= h($programme) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="assessment_status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Assessment Status</option>
                <option value="assessed" <?= $statusFilter === 'assessed' ? 'selected' : '' ?>>Assessed</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            </select>
            <div class="button-row">
                <button type="submit">Filter</button>
                <a class="button-link ghost" href="student_management.php">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Student</th>
                    <th>Programme</th>
                    <th>Internship</th>
                    <th>Assessment</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$records): ?>
                    <tr><td colspan="5" class="empty">No student records match the current filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <strong><?= h($record['student_name']) ?></strong><br />
                                <?= h($record['student_id']) ?><br />
                                <span class="helper"><?= h($record['notes'] ?: 'No note provided.') ?></span>
                            </td>
                            <td><?= h($record['programme']) ?></td>
                            <td>
                                <?= h($record['company_name']) ?><br />
                                <span class="helper">Assessor: <?= h($record['assessor_name']) ?></span><br />
                                <span class="helper"><?= h($record['start_date']) ?> to <?= h($record['end_date']) ?></span>
                            </td>
                            <td>
                                <?php if ($record['assessment_id']): ?>
                                    <span class="pill">Assessed</span><br />
                                    Final Mark: <?= number_format((float) $record['final_mark'], 2) ?><br />
                                    <span class="helper">Saved: <?= h((string) $record['assessed_at']) ?></span>
                                <?php else: ?>
                                    <span class="pill warn">Pending</span><br />
                                    No assessment saved yet.
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="button-link secondary" href="result_entry.php?student_id=<?= h($record['student_id']) ?>">Assess</a>
                                    <?php if (is_admin()): ?>
                                        <a class="button-link" href="student_management.php?edit=<?= h($record['student_id']) ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this student record?');">
                                            <input type="hidden" name="action" value="delete_student" />
                                            <input type="hidden" name="student_id" value="<?= h($record['student_id']) ?>" />
                                            <button class="danger" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
<?php render_footer(); ?>


