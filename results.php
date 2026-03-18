<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_login();

$pdo = db();
$user = current_user();

$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$export = $_GET['export'] ?? '';

$params = [];
$conditions = [];

if (is_assessor()) {
    $conditions[] = 'i.assessor_id = :assessor_id';
    $params['assessor_id'] = $user['user_id'];
}

if ($search !== '') {
    $conditions[] = '(s.student_id LIKE :search OR s.student_name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$fromSql = ' FROM assessments a
    JOIN internships i ON i.internship_id = a.internship_id
    JOIN students s ON s.student_id = i.student_id
    JOIN users u ON u.user_id = i.assessor_id' . $where;

$countStmt = $pdo->prepare('SELECT COUNT(*)' . $fromSql);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$selectSql = 'SELECT s.student_id, s.student_name, s.programme, s.company_name, u.full_name AS assessor_name,
        a.undertaking_tasks, a.health_safety, a.theoretical_knowledge, a.written_report, a.language_clarity,
        a.lifelong_learning, a.project_management, a.time_management, a.final_mark, a.comments, a.assessed_at' .
        $fromSql . ' ORDER BY a.assessed_at DESC, s.student_id';

if ($export === 'csv') {
    $stmt = $pdo->prepare($selectSql);
    $stmt->execute($params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="internship_results.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Student ID', 'Student Name', 'Programme', 'Company', 'Assessor', 'Final Mark', 'Assessed At', 'Comments']);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['student_id'],
            $row['student_name'],
            $row['programme'],
            $row['company_name'],
            $row['assessor_name'],
            $row['final_mark'],
            $row['assessed_at'],
            $row['comments'],
        ]);
    }
    fclose($output);
    exit;
}

$stmt = $pdo->prepare($selectSql . ' LIMIT :limit OFFSET :offset');
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll();

render_header('Result Viewing');
?>
<section class="panel">
    <div class="toolbar" style="margin-bottom: 18px;">
        <div>
            <h2 style="margin: 0;">Internship Results</h2>
            <p class="helper" style="margin: 8px 0 0;">
                <?= is_admin()
                    ? 'Admin can view results for all students.'
                    : 'Assessors can view detailed mark breakdowns for the students assigned to them.' ?>
            </p>
        </div>
        <div class="toolbar-actions">
            <a class="button-link secondary" href="results.php?<?= http_build_query(array_filter(['search' => $search, 'export' => 'csv'])) ?>">Export CSV</a>
            <button type="button" class="ghost" onclick="window.print()">Print</button>
        </div>
    </div>

    <form method="get" class="search-row" style="margin-bottom: 16px;">
        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by student ID or student name" />
        <div></div>
        <div class="button-row">
            <button type="submit">Search</button>
            <a class="button-link ghost" href="results.php">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Student</th>
                <th>Assessor</th>
                <th>Mark Breakdown</th>
                <th>Final Result</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$results): ?>
                <tr><td colspan="4" class="empty">No results found for the selected filter.</td></tr>
            <?php else: ?>
                <?php foreach ($results as $result): ?>
                    <tr>
                        <td>
                            <strong><?= h($result['student_name']) ?></strong><br />
                            <?= h($result['student_id']) ?><br />
                            <span class="helper"><?= h($result['programme']) ?></span><br />
                            <span class="helper"><?= h($result['company_name']) ?></span>
                        </td>
                        <td><?= h($result['assessor_name']) ?></td>
                        <td>
                            Tasks: <?= number_format((float) $result['undertaking_tasks'], 2) ?><br />
                            Safety: <?= number_format((float) $result['health_safety'], 2) ?><br />
                            Theory: <?= number_format((float) $result['theoretical_knowledge'], 2) ?><br />
                            Report: <?= number_format((float) $result['written_report'], 2) ?><br />
                            Language: <?= number_format((float) $result['language_clarity'], 2) ?><br />
                            Learning: <?= number_format((float) $result['lifelong_learning'], 2) ?><br />
                            Project: <?= number_format((float) $result['project_management'], 2) ?><br />
                            Time: <?= number_format((float) $result['time_management'], 2) ?>
                        </td>
                        <td>
                            <span class="pill">Saved</span><br />
                            Final Mark: <?= number_format((float) $result['final_mark'], 2) ?><br />
                            <span class="helper"><?= h((string) $result['assessed_at']) ?></span><br />
                            <span class="helper"><?= h($result['comments'] ?: 'No comments provided.') ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <span>Page <?= $page ?> of <?= $totalPages ?> | Total results: <?= $totalRows ?></span>
        <?php if ($page > 1): ?>
            <a class="button-link ghost" href="results.php?<?= http_build_query(array_filter(['search' => $search, 'page' => $page - 1])) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a class="button-link ghost" href="results.php?<?= http_build_query(array_filter(['search' => $search, 'page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
