<?php
session_start();
// grab database connection details from docker-compose environment variables

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

// user must be selected on homepage before using this page
if (!isset($_SESSION['user_id'])) {
    $errors[] = 'Please select a user on the homepage first.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $actor_id   = trim($_POST['actor_id']   ?? '');
    $content_id = trim($_POST['content_id'] ?? '');

    if (empty($actor_id))   $errors[] = 'Please select an actor.';
    if (empty($content_id)) $errors[] = 'Please select a title.';

    // make sure the selected actor actually exists in the database
    // (just in case someone manually edits the form values)
    if (!empty($actor_id)) {
        $chk = $conn->prepare('SELECT actor_id FROM actor WHERE actor_id = ?');
        $chk->bind_param('i', $actor_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) $errors[] = 'Selected actor does not exist.';
        $chk->close();
    }

    // same for content
    if (!empty($content_id)) {
        $chk = $conn->prepare('SELECT content_id FROM content WHERE content_id = ?');
        $chk->bind_param('i', $content_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows === 0) $errors[] = 'Selected title does not exist.';
        $chk->close();
    }

    // check if this actor is already assigned to this content
    // the actor_content table has a composite primary key so duplicates
    // would cause a SQL error anyway, but better to catch it here first
    // and show a friendly message instead
    if (!empty($actor_id) && !empty($content_id)) {
        $chk = $conn->prepare('SELECT actor_id FROM actor_content WHERE actor_id = ? AND content_id = ?');
        $chk->bind_param('ii', $actor_id, $content_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) $errors[] = 'This actor is already assigned to that title.';
        $chk->close();
    }

        // all checks passed - insert the new relationship into actor_content
        // this is the core of the use case (many-to-many assignment)
    if (empty($errors)) {
        $stmt = $conn->prepare('INSERT INTO actor_content (actor_id, content_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $actor_id, $content_id);
        if ($stmt->execute()) {// fetch names just for the success message
            $aRow = $conn->query("SELECT name FROM actor WHERE actor_id = $actor_id")->fetch_assoc();
            $cRow = $conn->query("SELECT title FROM content WHERE content_id = $content_id")->fetch_assoc();
            $success = "Actor '{$aRow['name']}' successfully assigned to '{$cRow['title']}'.";
            $_POST = []; // clear form after success
        } else {
            $errors[] = 'Insert failed: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Dropdowns for use case form
$actors_dropdown  = $conn->query('SELECT actor_id, name, num_of_awards FROM actor ORDER BY name');
// we use a CASE here to figure out if each content item is a movie or tv_show
// since that info lives in separate tables (movie/tv_show) not in content itself
$content_dropdown = $conn->query('
    SELECT c.content_id, c.title,
           CASE WHEN m.content_id IS NOT NULL THEN "movie" ELSE "tv_show" END AS type
    FROM content c
    LEFT JOIN movie m ON c.content_id = m.content_id
    ORDER BY c.title
');

// Dynamic filter options for the report (get genre)
$genres_result = $conn->query('SELECT DISTINCT genre FROM content ORDER BY genre');
$genres = [];
while ($g = $genres_result->fetch_assoc()) {
    $genres[] = $g['genre'];
}
// get actors for the report filter dropdown
$actors_result = $conn->query('SELECT actor_id, name FROM actor ORDER BY name');
$actors_filter = [];
while ($a = $actors_result->fetch_assoc()) {
    $actors_filter[] = $a;
}

// Selected filter values — empty string means no filter applied
$selected_genre    = $_GET['genre']    ?? '';
$selected_actor_id = $_GET['actor_id'] ?? '';

// Build analytics query dynamically based on active filters
$where_clauses = [];
$params        = [];
$types         = '';

if (!empty($selected_genre)) {
    $where_clauses[] = 'c.genre = ?';
    $params[]        = $selected_genre;
    $types          .= 's';
}
if (!empty($selected_actor_id)) {
    $where_clauses[] = 'a.actor_id = ?';
    $params[]        = (int)$selected_actor_id;
    $types          .= 'i';
}

// only add WHERE clause if at least one filter is active
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// this is the main analytics query from MS1
// joins actor -> actor_content -> content to show which actors
// are assigned to which content, filtered by genre and/or actor
// limited to 5
$report_sql = "
    SELECT
        a.name        AS actor_name,
        c.title       AS content_title,
        c.genre
    FROM actor a
    JOIN actor_content ac ON a.actor_id    = ac.actor_id
    JOIN content c        ON ac.content_id = c.content_id
    $where_sql
    LIMIT 5
";

// if filters are active we use prepared statements to avoid SQL injection
// otherwise just run the query directly
$report_rows = [];
if (!empty($params)) {
    $stmt = $conn->prepare($report_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $report_rows[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query($report_sql);
    while ($row = $result->fetch_assoc()) {
        $report_rows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign Actor - Watchfolio</title>
    <link rel="stylesheet" href="assets/css/pixel-theme.css">
    <style>
        :root {
            --primary: #ffaac7; --secondary: #e9c3d5; --accent: #f3c3cf;
            --danger: #ffc9e6; --bg: #fbd6ee; --card-bg: #ffffff; --text: #333333;
        }
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
        .filter-form { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { margin-top: 0; font-weight: 700; font-size: 14px; }
        .filter-group select { width: auto; min-width: 160px; margin-top: 0; }
        .filter-form button { margin-top: 0; padding: 10px 20px; }
        .active-filters { font-size: 13px; color: #666; margin-bottom: 8px; }
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

        <!-- USE CASE FORM -->
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

            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="index.php" class="btn">
                    <span class="pixel-symbol pixel-user small" aria-hidden="true"></span>
                    Select a user on the homepage first
                </a>
            <?php else: ?>
            <form method="POST">
                <h2><span class="pixel-symbol pixel-movie" aria-hidden="true"></span>Select Title</h2>
                <label>Title *</label>
                <select name="content_id">
                    <option value="">-- Select a Title --</option>
                    <?php while ($c = $content_dropdown->fetch_assoc()): ?>
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
                    <?php while ($a = $actors_dropdown->fetch_assoc()): ?>
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
            <?php endif; ?>
        </div>

        <!-- ANALYTICS REPORT -->
        <div class="card">
            <h2><span class="pixel-symbol pixel-star" aria-hidden="true"></span>Analytics: Actor Assignments</h2>
            <p>Shows up to 5 actor-content assignments. Filter by genre or actor to narrow results.</p>

            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="genre">Genre</label>
                    <select name="genre" id="genre">
                        <option value="">-- All Genres --</option>
                        <?php foreach ($genres as $g): ?>
                            <option value="<?= htmlspecialchars($g) ?>" <?= $g === $selected_genre ? 'selected' : '' ?>>
                                <?= htmlspecialchars($g) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="actor_id">Actor</label>
                    <select name="actor_id" id="actor_id">
                        <option value="">-- All Actors --</option>
                        <?php foreach ($actors_filter as $a): ?>
                            <option value="<?= (int)$a['actor_id'] ?>" <?= $a['actor_id'] == $selected_actor_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">Apply</button>
                <?php if (!empty($selected_genre) || !empty($selected_actor_id)): ?>
                    <a href="?" class="btn" style="margin-top:0; padding: 10px 20px; background: var(--secondary);">Clear</a>
                <?php endif; ?>
            </form>

            <?php
                $active = [];
                if (!empty($selected_genre)) $active[] = 'Genre: <strong>' . htmlspecialchars($selected_genre) . '</strong>';
                if (!empty($selected_actor_id)) {
                    foreach ($actors_filter as $a) {
                        if ($a['actor_id'] == $selected_actor_id) {
                            $active[] = 'Actor: <strong>' . htmlspecialchars($a['name']) . '</strong>';
                            break;
                        }
                    }
                }
                if (!empty($active)) echo '<p class="active-filters">Active filters: ' . implode(' &amp; ', $active) . '</p>';
                else echo '<p class="active-filters">Showing all results.</p>';
            ?>

            <?php if (!empty($report_rows)): ?>
                <table>
                    <tr>
                        <th>Actor</th>
                        <th>Content Title</th>
                        <th>Genre</th>
                    </tr>
                    <?php foreach ($report_rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['actor_name']) ?></td>
                            <td><?= htmlspecialchars($row['content_title']) ?></td>
                            <td><?= htmlspecialchars($row['genre']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No results found. Try assigning an actor first or adjusting the filters.</p>
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