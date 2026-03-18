<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function render_header(string $title): void
{
    $user = current_user();
    $flash = consume_flash();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
<?php if ($user): ?>
    <header class="site-header">
        <div>
            <h1><?= h(app_name()) ?></h1>
            <p>Role-based internship assessment system built with PHP and MySQL.</p>
        </div>
        <div class="session-box">
            <strong><?= h($user['full_name']) ?></strong><br />
            <span><?= h($user['role']) ?> account</span><br />
            <span><?= h($user['username']) ?></span>
        </div>
    </header>
    <nav class="site-nav">
        <a href="student_management.php">Students</a>
        <a href="result_entry.php">Assessment Entry</a>
        <a href="results.php">Results</a>
        <?php if (is_admin()): ?>
            <a href="user_management.php">Assessor Accounts</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </nav>
<?php endif; ?>
<main class="page-shell<?= $user ? '' : ' auth-shell' ?>">
    <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>
<?php
}

function render_footer(): void
{
    ?>
</main>
</body>
</html>
<?php
}
