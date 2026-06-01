<?php
session_start();

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $_SESSION['user_id'] = (int)$_POST['user_id'];
    $_SESSION['username'] = $_POST['username'];
}

$users = $conn->query('SELECT user_id, username FROM app_user ORDER BY username');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Watchfolio</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .card { background: #f5f5f5; padding: 20px; margin: 10px 0; border-radius: 8px; }
        a { color: #333; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 12px 24px; background: #333; color: white; border-radius: 5px; margin-top: 8px; }
        .btn:hover { background: #555; color: white; }
        .btn-red { background: #c0392b; }
        .btn-red:hover { background: #e74c3c; }
        .user-bar { background: #333; color: white; padding: 10px 20px; margin: -40px -20px 30px -20px; display: flex; align-items: center; gap: 15px; }
        .user-bar select { padding: 5px; border-radius: 4px; }
        .user-bar button { padding: 5px 15px; background: #555; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .user-bar button:hover { background: #777; }
    </style>
</head>
<body>
    <div class="user-bar">
        <span>🎬 Watchfolio</span>
        <form method="POST" style="display:flex; align-items:center; gap:10px; margin:0;">
            <label style="color:white;">Acting as:</label>
            <select name="user_id" onchange="this.form.submit()">
                <option value="">-- Select User --</option>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <option value="<?= $user['user_id'] ?>"
                        data-username="<?= htmlspecialchars($user['username']) ?>"
                        <?= (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['user_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['username']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <input type="hidden" name="username" id="username_hidden">
        </form>
        <?php if (isset($_SESSION['username'])): ?>
            <span>👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
        <?php endif; ?>
    </div>

    <h1>Welcome to Watchfolio</h1>

    <div class="card">
        <h2>⚙️ Data Setup</h2>
        <p>Reset and fill the database with randomized data.</p>
        <a href="generate_database.php" class="btn btn-red">🔄 Generate Data</a>
    </div>

    <div class="card">
        <h2>📝 Use Cases</h2>
        <a href="add_movie.php" class="btn">🎬 Add New Movie</a>
    </div>
</body>
<script>
    // Pass username to hidden input when dropdown changes
    const select = document.querySelector('select[name="user_id"]');
    const hidden = document.getElementById('username_hidden');
    select.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        hidden.value = opt.getAttribute('data-username') || '';
    });
</script>
</html>