<?php
session_start();

$dbHost     = getenv('DB_HOST')     ?: 'mariadb';
$dbUser     = getenv('DB_USER')     ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName     = getenv('DB_NAME')     ?: 'watchfolio';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$success = '';
$errors  = [];

if (!isset($_SESSION['user_id'])) {
    $errors[] = 'Please select a user on the homepage first.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $actor_id   = trim($_POST['actor_id']   ?? '');
    $content_id = trim($_POST['content_id'] ?? '');

    if (empty($actor_id))   $errors[] = 'Please select an actor.';
    if (empty($content_id)) $errors[] = 'Please select a title.';

    // Check actor exists
    if (!empty($actor_id)) {
        $chk = $conn->prepare('SELECT actor_id FROM actor WHERE actor_id = ?');
        $chk->bind_param('i', $actor_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) $errors[] = 'Selected actor does not exist.';
        $chk->close();
    }

    // Check content exists
    if (!empty($content_id)) {
        $chk = $conn->prepare('SELECT content_id FROM content WHERE content_id = ?');
        $chk->bind_param('i', $content_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) $errors[] = 'Selected title does not exist.';
        $chk->close();
    }

    // Check duplicate assignment
    if (!empty($actor_id) && !empty($content_id)) {
        $chk = $conn->prepare('SELECT actor_id FROM actor_content WHERE actor_id = ? AND content_id = ?');
        $chk->bind_param('ii', $actor_id, $content_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) $errors[] = 'This actor is already assigned to that title.';
        $chk->close();
    }

    if (empty($errors)) {
        $stmt = $conn->prepare('INSERT INTO actor_content (actor_id, content_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $actor_id, $content_id);
        if ($stmt->execute()) {
            // Fetch names for the success message
            $aRow = $conn->query("SELECT name FROM actor WHERE actor_id = $actor_id")->fetch_assoc();
            $cRow = $conn->query("SELECT title FROM content WHERE content_id = $content_id")->fetch_assoc();
            $success = "Actor '{$aRow['name']}' successfully assigned to '{$cRow['title']}'.";
            $_POST = [];
        } else {
            $errors[] = 'Insert failed: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Dropdowns
$actors  = $conn->query('SELECT actor_id, name, num_of_awards FROM actor ORDER BY name');
$content = $conn->query('
    SELECT c.content_id, c.title,
           CASE WHEN m.content_id IS NOT NULL THEN "movie" ELSE "tv_show" END AS type
    FROM content c
    LEFT JOIN movie m ON c.content_id = m.content_id
    ORDER BY c.title
');

// Analytics: top actors by number of titles they appear in
$report = $conn->query('
    SELECT a.name AS actor_name, a.num_of_awards, COUNT(ac.content_id) AS total_titles
    FROM actor a
    JOIN actor_content ac ON a.actor_id = ac.actor_id
    GROUP BY a.actor_id, a.name, a.num_of_awards
    ORDER BY total_titles DESC
    LIMIT 10
');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign Actor - Watchfolio</title>
    <link rel="stylesheet" href="assets/css/pixel-theme.css">
    <style>
        :root { --primary: #ffaac7; --secondary: #e9c3d5; --accent: #f3c3cf; --danger: #ffc9e6; --bg: #fbd6ee; --card-bg: #ffffff; --text: #333333; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: var(--bg); color: var(--text); line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .card { background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .card h2 { margin-top: 0; color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; gap: 8px; }
        a { color: var(--primary); text-decoration: none; font-weight: 500; }
        a:hover { text-decoration: underline; }
        .btn, button { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--primary); color: #333; border-radius: 8px; border: none; margin-top: 12px; transition: all 0.2s ease; text-decoration: none; font-weight: 500; cursor: pointer; }
        .btn:hover, button:hover { background: var(--secondary); color: #333; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-decoration: none; }
        label { display: block; margin-top: 14px; font-weight: 700; }
        input, select { width: 100%; padding: 10px; margin-top: 6px; border-radius: 8px; border: 1px solid var(--secondary); box-sizing: border-box; font-size: 15px; }
        .success { background: #e8ffe8; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .error { background: #ffe8ee; color: #9f1239; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .error ul { margin: 10px 0 0 0; padding-left: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; }
        th { background: var(--primary); }
        .header { text-align: center; margin: 40px 0 30px; }
        .header h1 { font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="user-bar">
        <span class="brand"><span class="pixel-symbol pixel-user" aria-hidden="true"></span>Assign Actor</span>
        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-info"><span class="pixel-symbol pixel-user small" aria-hidden="true"></span><?= htmlspecialchars($_SESSION['username']) ?></div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="header">
            <h1>Assign Actor</h1>
            <p>Link an actor to a movie or TV show in the SQL database.</p>
        </div>

        <div class="card">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <h2><span class="pixel-symbol pixel-movie" aria-hidden="true"></span>Select Title</h2>
                <label>Title *</label>
                <select name="content_id">
                    <option value="">-- Select a Title --</option>
                    <?php while ($c = $content->fetch_assoc()): ?>
                        <option value="<?= $c['content_id'] ?>"
                            <?= (isset($_POST['content_id']) && $_POST['content_id'] == $c['content_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['type']) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>

                <h2 style="margin-top: 25px;"><span class="pixel-symbol pixel-user" aria-hidden="true"></span>Select Actor</h2>
                <label>Actor *</label>
                <select name="actor_id">
                    <option value="">-- Select an Actor --</option>
                    <?php while ($a = $actors->fetch_assoc()): ?>
                        <option value="<?= $a['actor_id'] ?>"
                            <?= (isset($_POST['actor_id']) && $_POST['actor_id'] == $a['actor_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['name']) ?> (<?= (int)$a['num_of_awards'] ?> awards)
                        </option>
                    <?php endwhile; ?>
                </select>

                <button type="submit">
                    <span class="pixel-symbol pixel-star small" aria-hidden="true"></span>
                    Assign Actor
                </button>
            </form>
        </div>

        <div class="card">
            <h2><span class="pixel-symbol pixel-star" aria-hidden="true"></span>Analytics: Top Actors by Number of Titles</h2>
            <p>Actors ranked by how many movies and TV shows they appear in.</p>
            <?php if ($report && $report->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>Actor</th>
                        <th>Awards</th>
                        <th>Total Titles</th>
                    </tr>
                    <?php while ($row = $report->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['actor_name']) ?></td>
                            <td><?= (int)$row['num_of_awards'] ?></td>
                            <td><?= (int)$row['total_titles'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php else: ?>
                <p>No actor assignments yet.</p>
            <?php endif; ?>
        </div>

        <a href="index.php" class="btn">
            <span class="pixel-symbol pixel-movie small" aria-hidden="true"></span>
            Return to Homepage
        </a>
    </div>
    <script src="assets/js/sparkle-cursor.js"></script>
</body>
</html>
