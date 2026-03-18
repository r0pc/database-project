<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_assessor') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($fullName === '' || $username === '') {
            flash('error', 'Full name and username are required.');
            redirect('user_management.php');
        }

        if ($userId > 0) {
            $existing = $pdo->prepare('SELECT user_id FROM users WHERE username = :username AND user_id <> :user_id');
            $existing->execute(['username' => $username, 'user_id' => $userId]);
            if ($existing->fetch()) {
                flash('error', 'Username already exists.');
                redirect('user_management.php');
            }

            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, username = :username, password_hash = :password_hash WHERE user_id = :user_id AND role = \'Assessor\'');
                $stmt->execute([
                    'full_name' => $fullName,
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'user_id' => $userId,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, username = :username WHERE user_id = :user_id AND role = \'Assessor\'');
                $stmt->execute([
                    'full_name' => $fullName,
                    'username' => $username,
                    'user_id' => $userId,
                ]);
            }

            flash('success', 'Assessor account updated.');
            redirect('user_management.php');
        }

        if ($password === '') {
            flash('error', 'Password is required when creating a new assessor.');
            redirect('user_management.php');
        }

        $existing = $pdo->prepare('SELECT user_id FROM users WHERE username = :username LIMIT 1');
        $existing->execute(['username' => $username]);
        if ($existing->fetch()) {
            flash('error', 'Username already exists.');
            redirect('user_management.php');
        }

        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (:username, :password_hash, :full_name, \'Assessor\')');
        $stmt->execute([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $fullName,
        ]);

        flash('success', 'Assessor account created.');
        redirect('user_management.php');
    }

    if ($action === 'delete_assessor') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $check = $pdo->prepare('SELECT COUNT(*) FROM internships WHERE assessor_id = :user_id');
        $check->execute(['user_id' => $userId]);
        if ((int) $check->fetchColumn() > 0) {
            flash('error', 'This assessor is assigned to internship records and cannot be deleted yet.');
            redirect('user_management.php');
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :user_id AND role = \'Assessor\'');
        $stmt->execute(['user_id' => $userId]);
        flash('success', 'Assessor account deleted.');
        redirect('user_management.php');
    }
}

$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT user_id, full_name, username FROM users WHERE user_id = :user_id AND role = 'Assessor'");
    $stmt->execute(['user_id' => (int) $_GET['edit']]);
    $editUser = $stmt->fetch() ?: null;
}

$assessors = $pdo->query("SELECT user_id, full_name, username FROM users WHERE role = 'Assessor' ORDER BY full_name")->fetchAll();

render_header('Assessor Account Management');
?>
<section class="content-grid">
    <article class="panel">
        <h2><?= $editUser ? 'Edit Assessor' : 'Create Assessor' ?></h2>
        <p class="helper">Admin users manage lecturer/supervisor accounts here.</p>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_assessor" />
            <input type="hidden" name="user_id" value="<?= h((string) ($editUser['user_id'] ?? '0')) ?>" />
            <div>
                <label for="full_name">Full Name</label>
                <input id="full_name" name="full_name" type="text" value="<?= h($editUser['full_name'] ?? '') ?>" required />
            </div>
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="<?= h($editUser['username'] ?? '') ?>" required />
            </div>
            <div>
                <label for="password">Password<?= $editUser ? ' (leave blank to keep current password)' : '' ?></label>
                <input id="password" name="password" type="password" <?= $editUser ? '' : 'required' ?> />
            </div>
            <div class="button-row">
                <button type="submit"><?= $editUser ? 'Update Account' : 'Create Account' ?></button>
                <a class="button-link ghost" href="user_management.php">Clear</a>
            </div>
        </form>
    </article>

    <article class="panel">
        <h2>Existing Assessor Accounts</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$assessors): ?>
                    <tr><td colspan="3" class="empty">No assessor accounts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($assessors as $assessor): ?>
                        <tr>
                            <td><?= h($assessor['full_name']) ?></td>
                            <td><?= h($assessor['username']) ?></td>
                            <td>
                                <div class="table-actions">
                                    <a class="button-link" href="user_management.php?edit=<?= (int) $assessor['user_id'] ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this assessor account?');">
                                        <input type="hidden" name="action" value="delete_assessor" />
                                        <input type="hidden" name="user_id" value="<?= (int) $assessor['user_id'] ?>" />
                                        <button class="danger" type="submit">Delete</button>
                                    </form>
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
