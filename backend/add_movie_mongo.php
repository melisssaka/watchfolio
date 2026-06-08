<?php
session_start();
$success = '';
$errors = [];
$directors = [];
$report = null;
$isMigrated = false;
$mongodb = null;

if (!isset($_SESSION['user_id'])) {
    $errors[] = 'Please select a user on the homepage first.';
}

try {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception('MongoDB dependencies are not installed yet.');
    }
    require_once $autoloadPath;
    $mongoHost = getenv('MONGO_HOST') ?: 'mongodb';
    $mongoPort = getenv('MONGO_PORT') ?: '27017';
    $mongo = new MongoDB\Client("mongodb://$mongoHost:$mongoPort");
    $mongodb = $mongo->watchfolio_db;
    $migrationStatus = $mongodb->config->findOne(['_id' => 'migration_status']);
    $isMigrated = $migrationStatus && !empty($migrationStatus['migrated']);
    if (!$isMigrated) {
        $errors[] = 'Please migrate SQL data to MongoDB before using the MongoDB movie page.';
    }
} catch (Exception $e) {
    $errors[] = 'MongoDB connection failed: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $isMigrated && $mongodb) {
    $userId = (int) $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? ('username' . $userId);
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

    // Check duplicate movie (checks for the same title from the same director)
    if (!empty($title) && !empty($director_id)) {
        $existing = $mongodb->content->findOne([
            'title' => $title,
            'type' => 'movie',
            'movie_details.director.director_id' => (int) $director_id
        ]);
        if ($existing) {
            $errors[] = 'A movie with this title already exists for the selected director.';
        }
    }

    // Check director exists
    if (!empty($director_id)) {
        $directorExists = $mongodb->director->findOne(['_id' => (int) $director_id]);
        if (!$directorExists) {
            $errors[] = 'Selected director does not exist in the database.';
        }
    }

    if (empty($errors)) {
        // Get next content_id
        $maxContent = $mongodb->content->aggregate([
            ['$group' => ['_id' => null, 'max_id' => ['$max' => '$_id']]]
        ])->toArray();
        $content_id = (!empty($maxContent) ? (int) $maxContent[0]['max_id'] : 0) + 1;

        // Get director info
        $directorDoc = $mongodb->director->findOne(['_id' => (int) $director_id]);

        $insertResult = $mongodb->content->insertOne([
            '_id' => $content_id,
            'title' => $title,
            'genre' => $genre,
            'release_year' => (int) $release_year,
            'type' => 'movie',
            'created_by_user' => $userId,
            'movie_details' => [
                'duration' => (int) $duration,
                'box_office' => (int) $box_office,
                'director' => [
                    'director_id' => (int) $director_id,
                    'name' => (string) $directorDoc['name'],
                    'nationality' => (string) $directorDoc['nationality']
                ]
            ],
            'tv_show_details' => null,
            'actors' => [],
            'reviews' => []
        ]);

        if ($insertResult->getInsertedCount() > 0) {
            $success = "Movie '$title' was successfully added to MongoDB! (Content ID: $content_id)";
            $_POST = [];
        } else {
            $errors[] = 'Failed to insert movie into MongoDB.';
        }
    }
}

// Fetch directors for dropdown
if ($isMigrated && $mongodb) {
    $directors = $mongodb->director->find([], ['sort' => ['name' => 1]]);

    // Analytics report: Drama directors ranked by movie count in MongoDB
   $reportResult = $mongodb->content->aggregate([
        ['$match' => ['type' => 'movie', 'genre' => 'Drama']],
        ['$group' => [
            '_id' => '$movie_details.director.director_id',
            'director_name' => ['$first' => '$movie_details.director.name'],
            'nationality' => ['$first' => '$movie_details.director.nationality'],
            'total_movies' => ['$sum' => 1]
        ]],
        ['$sort' => ['total_movies' => -1]]
    ])->toArray();
    $report = $reportResult;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Movie (MongoDB) - Watchfolio</title>
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
        <span class="brand"><span class="pixel-symbol pixel-movie" aria-hidden="true"></span>Add New Movie (MongoDB)</span>
        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-info"><span class="pixel-symbol pixel-user small" aria-hidden="true"></span><?= htmlspecialchars($_SESSION['username']) ?></div>
        <?php endif; ?>
    </div>
    <div class="container">
        <div class="header">
            <h1>Add New Movie (MongoDB)</h1>
            <p>Add a new movie to the Watchfolio MongoDB database.</p>
        </div>

        <div class="card">
            <?php foreach ($errors as $error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!$isMigrated): ?>
                <a href="migrate.php" class="btn">
                    <span class="pixel-symbol pixel-rocket small" aria-hidden="true"></span>
                    Migrate SQL Data to MongoDB first
                </a>
            <?php else: ?>
                <form method="POST">
                    <h2><span class="pixel-symbol pixel-note" aria-hidden="true"></span>Content Details</h2>
                    <label>Title *</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. Oppenheimer">
                    <label>Genre *</label>
                    <input type="text" name="genre" value="<?= htmlspecialchars($_POST['genre'] ?? '') ?>" placeholder="e.g. Drama">
                    <label>Release Year *</label>
                    <input type="number" name="release_year" value="<?= htmlspecialchars($_POST['release_year'] ?? '') ?>" placeholder="e.g. 2023" min="1888" max="2100">

                    <h2 style="margin-top: 25px;"><span class="pixel-symbol pixel-movie" aria-hidden="true"></span>Movie Details</h2>
                    <label>Duration (minutes) *</label>
                    <input type="number" name="duration" value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>" placeholder="e.g. 180" min="1">
                    <label>Box Office ($) *</label>
                    <input type="number" name="box_office" value="<?= htmlspecialchars($_POST['box_office'] ?? '') ?>" placeholder="e.g. 952000000" min="0">

                    <h2 style="margin-top: 25px;"><span class="pixel-symbol pixel-user" aria-hidden="true"></span>Director</h2>
                    <label>Select Director *</label>
                    <select name="director_id">
                        <option value="">-- Select a Director --</option>
                        <?php foreach ($directors as $dir): ?>
                            <option value="<?= (int) $dir['_id'] ?>"
                                <?= (isset($_POST['director_id']) && $_POST['director_id'] == $dir['_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dir['name']) ?> (<?= htmlspecialchars($dir['nationality']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">
                        <span class="pixel-symbol pixel-star small" aria-hidden="true"></span>
                        Save Movie
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2><span class="pixel-symbol pixel-star" aria-hidden="true"></span>Analytics Report: Drama Directors by Movie Count (MongoDB)</h2>
            <p>Directors who have directed Drama movies, ranked by total number of Drama films.</p>
            <?php if (!empty($report)): ?>
                <table>
                    <tr>
                        <th>Director</th>
                        <th>Nationality</th>
                        <th>Total Movies</th>
                    </tr>
                    <?php foreach ($report as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['director_name']) ?></td>
                            <td><?= htmlspecialchars($row['nationality']) ?></td>
                            <td><?= (int) $row['total_movies'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php elseif ($isMigrated): ?>
                <p>No Drama movies in MongoDB yet. Add some movies to see the report!</p>
            <?php else: ?>
                <p>Migrate SQL data to MongoDB first to view the MongoDB report.</p>
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