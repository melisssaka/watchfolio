<?php
session_start();
$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$success = '';
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and correct inputs
    $title = trim($_POST['title'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $release_year = trim($_POST['release_year'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $box_office = trim($_POST['box_office'] ?? '');
    $director_id = trim($_POST['director_id'] ?? '');

    // Validation
    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($genre)) $errors[] = 'Genre is required.';
    if (empty($release_year) || !is_numeric($release_year) || $release_year < 1888 || $release_year > 2100) {
        $errors[] = 'Release year must be a valid year (1888-2100).';
    }
    if (empty($duration) || !is_numeric($duration) || $duration <= 0) {
        $errors[] = 'Duration must be a positive number (in minutes).';
    }
    if (empty($box_office) || !is_numeric($box_office) || $box_office < 0) {
        $errors[] = 'Box office must be a non-negative number.';
    }
    if (empty($director_id)) $errors[] = 'Please select a director.';

    // Check for duplicate movie (same title + same director)
    if (!empty($title) && !empty($director_id)) {
        $checkDup = $conn->prepare('
            SELECT c.content_id FROM content c
            JOIN movie m ON c.content_id = m.content_id
            WHERE c.title = ? AND m.director_id = ?
        ');
        $checkDup->bind_param('si', $title, $director_id);
        $checkDup->execute();
        $checkDup->store_result();
        if ($checkDup->num_rows > 0) {
            $errors[] = 'A movie with this title already exists for the selected director.';
        }
        $checkDup->close();
    }

    // Check director exists
    if (!empty($director_id)) {
        $checkDir = $conn->prepare('SELECT director_id FROM director WHERE director_id = ?');
        $checkDir->bind_param('i', $director_id);
        $checkDir->execute();
        $checkDir->store_result();
        if ($checkDir->num_rows === 0) {
            $errors[] = 'Selected director does not exist in the database.';
        }
        $checkDir->close();
    }

    // Insert if no errors
    if (empty($errors)) {
        // Get next content_id
        $result = $conn->query('SELECT MAX(content_id) AS max_id FROM content');
        $row = $result->fetch_assoc();
        $content_id = ($row['max_id'] ?? 0) + 1;

        $created_by_user = $_SESSION['user_id'] ?? 0;
        if ($created_by_user === 0) {
            $errors[] = 'No user selected. Please go back to the homepage and select a user first.';
        }

        // Insert into content
        $stmt1 = $conn->prepare('INSERT INTO content (content_id, title, genre, release_year, created_by_user) VALUES (?, ?, ?, ?, ?)');
        $stmt1->bind_param('issii', $content_id, $title, $genre, $release_year, $created_by_user);

        if ($stmt1->execute()) {
            // Insert into movie
            $stmt2 = $conn->prepare('INSERT INTO movie (content_id, duration, box_office, director_id) VALUES (?, ?, ?, ?)');
            $stmt2->bind_param('iiii', $content_id, $duration, $box_office, $director_id);

            if ($stmt2->execute()) {
                $success = "Movie '$title' was successfully added! (Content ID: $content_id)";
            } else {
                $errors[] = 'Failed to insert movie record: ' . $stmt2->error;
                // Rollback content insert
                $conn->query("DELETE FROM content WHERE content_id = $content_id");
            }
            $stmt2->close();
        } else {
            $errors[] = 'Failed to insert content record: ' . $stmt1->error;
        }
        $stmt1->close();
    }
}

// Fetch directors for dropdown
$directors = $conn->query('SELECT director_id, name, nationality FROM director ORDER BY name');

// Analytics report: Drama directors ranked by movie count
$report = $conn->query('
    SELECT d.name AS director_name, d.nationality, c.genre, COUNT(m.content_id) AS total_movies
    FROM director d
    JOIN movie m ON d.director_id = m.director_id
    JOIN content c ON m.content_id = c.content_id
    WHERE c.genre = "Drama"
    GROUP BY d.director_id, d.name, d.nationality, c.genre
    ORDER BY total_movies DESC
');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Movie - Watchfolio</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .error { color: red; background: #ffe0e0; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .success { color: green; background: #e0ffe0; padding: 10px; border-radius: 5px; margin: 10px 0; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        button { margin-top: 20px; padding: 10px 20px; background: #333; color: white; border: none; cursor: pointer; border-radius: 5px; }
        button:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        nav { margin-bottom: 20px; }
        nav a { margin-right: 15px; color: #333; }
    </style>
</head>
<body>
    <nav><a href="index.php">🎬 Home</a></nav>
    <h1>Add New Movie</h1>

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
        <h2>Content Details</h2>
        <label>Title *</label>
        <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. Oppenheimer">

        <label>Genre *</label>
        <input type="text" name="genre" value="<?= htmlspecialchars($_POST['genre'] ?? '') ?>" placeholder="e.g. Drama">

        <label>Release Year *</label>
        <input type="number" name="release_year" value="<?= htmlspecialchars($_POST['release_year'] ?? '') ?>" placeholder="e.g. 2023" min="1888" max="2100">

        <h2>Movie Details</h2>
        <label>Duration (minutes) *</label>
        <input type="number" name="duration" value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>" placeholder="e.g. 180" min="1">

        <label>Box Office ($) *</label>
        <input type="number" name="box_office" value="<?= htmlspecialchars($_POST['box_office'] ?? '') ?>" placeholder="e.g. 952000000" min="0">

        <h2>Director</h2>
        <label>Select Director *</label>
        <select name="director_id">
            <option value="">-- Select a Director --</option>
            <?php while ($dir = $directors->fetch_assoc()): ?>
                <option value="<?= $dir['director_id'] ?>"
                    <?= (isset($_POST['director_id']) && $_POST['director_id'] == $dir['director_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dir['name']) ?> (<?= htmlspecialchars($dir['nationality']) ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit">💾 Save Movie</button>
    </form>

    <hr style="margin-top: 40px;">
    <h2>📊 Analytics Report: Drama Directors by Movie Count</h2>
    <p>Directors who have directed Drama movies, ranked by total number of Drama films.</p>

    <?php if ($report && $report->num_rows > 0): ?>
        <table>
            <tr>
                <th>Director</th>
                <th>Nationality</th>
                <th>Genre</th>
                <th>Total Movies</th>
            </tr>
            <?php while ($row = $report->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['director_name']) ?></td>
                    <td><?= htmlspecialchars($row['nationality']) ?></td>
                    <td><?= htmlspecialchars($row['genre']) ?></td>
                    <td><?= $row['total_movies'] ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No Drama movies in the database yet. Add some movies to see the report!</p>
    <?php endif; ?>

</body>
</html>