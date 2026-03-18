<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

if (current_user()) {
    redirect('student_management.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if ($username === '' || $password === '' || $role === '') {
        flash('error', 'Please complete role, username, and password.');
        redirect('index.php');
    }

    $stmt = db()->prepare('SELECT user_id, username, password_hash, full_name, role FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== $role || !password_verify($password, $user['password_hash'])) {
        flash('error', 'Invalid login details.');
        redirect('index.php');
    }

    $_SESSION['user'] = [
        'user_id' => (int) $user['user_id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
    ];

    flash('success', 'Login successful.');
    redirect('student_management.php');
}

render_header('Login');
?>
<section class="login-shell">
    <div class="login-hero">
        <div class="pill">COMP1044 PHP + MySQL</div>
        <h1>Internship Result Management System</h1>
        <p>This implementation matches the coursework requirements: secure login, admin and assessor roles, internship assignment, result entry, and result viewing.</p>
    </div>
    <div class="login-panel panel">
        <h2>System Login</h2>
        <p class="helper">Use the seeded accounts from the SQL file or the assessor accounts created by the admin.</p>
        <p class="helper">Default accounts: admin01 / Admin@123 and assessor01 / Assess@123.</p>
        <form method="post" class="form-grid">
            <div>
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="">Select role</option>
                    <option value="Admin">Admin</option>
                    <option value="Assessor">Assessor</option>
                </select>
            </div>
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" type="text" required />
            </div>
            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required />
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</section>
<?php render_footer(); ?>
