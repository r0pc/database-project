<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require dirname(__DIR__) . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $config;
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function app_name(): string
{
    global $config;
    return $config['app']['name'];
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consume_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'Admin';
}

function is_assessor(): bool
{
    return (current_user()['role'] ?? '') === 'Assessor';
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please log in first.');
        redirect('index.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Admin access is required for that page.');
        redirect('student_management.php');
    }
}

function fetch_assessors(): array
{
    $stmt = db()->query("SELECT user_id, full_name, username FROM users WHERE role = 'Assessor' ORDER BY full_name");
    return $stmt->fetchAll();
}

function fetch_dashboard_stats(?int $assessorId = null): array
{
    $pdo = db();
    $params = [];
    $where = '';

    if ($assessorId !== null) {
        $where = ' WHERE i.assessor_id = :assessor_id ';
        $params['assessor_id'] = $assessorId;
    }

    $studentSql = 'SELECT COUNT(*) FROM internships i' . $where;
    $studentStmt = $pdo->prepare($studentSql);
    $studentStmt->execute($params);
    $totalStudents = (int) $studentStmt->fetchColumn();

    $markSql = 'SELECT COUNT(a.assessment_id) AS assessed_count, AVG(a.final_mark) AS average_mark, MAX(a.final_mark) AS highest_mark
        FROM internships i
        LEFT JOIN assessments a ON a.internship_id = i.internship_id' . $where;
    $markStmt = $pdo->prepare($markSql);
    $markStmt->execute($params);
    $result = $markStmt->fetch() ?: [];

    return [
        'total_students' => $totalStudents,
        'assessed_count' => (int) ($result['assessed_count'] ?? 0),
        'average_mark' => $result['average_mark'] !== null ? number_format((float) $result['average_mark'], 2) : '0.00',
        'highest_mark' => $result['highest_mark'] !== null ? number_format((float) $result['highest_mark'], 2) : '0.00',
    ];
}

function log_app_error(string $message, Throwable $exception): void
{
    error_log($message . ' | ' . $exception->getMessage() . ' | ' . $exception->getFile() . ':' . $exception->getLine());
}

