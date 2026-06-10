<?php
/**
 * index.php — Watchfolio homepage
 * Displays user selector, migration status, latest reviews, and navigation.
 * Cover images sourced from Pinterest (decorative use only) 
 * Sparkle cursor: "Tinkerbell Magic Sparkle" via snazzyspace.com
 * Pixel emoji icons: AI-generated (Codex) only for visiual use, not for ms2 requirements approved per course email (Adrian Hofer, June 2026)
 * MongoDB PHP Library: https://www.mongodb.com/docs/php-library/current/
 * Docker PHP image: https://hub.docker.com/_/php
*/
session_start();
require_once __DIR__ . '/db_mode.php';

$mongoStatus = [
    'migrated'    => false,
    'migrated_at' => null,
];
$mongodb = null;

$migrateMessage = null;
$migrateError   = null;
if (!empty($_SESSION['migrate_success'])) {
    $migrateMessage = $_SESSION['migrate_success'];
    unset($_SESSION['migrate_success']);
}
if (!empty($_SESSION['migrate_error'])) {
    $migrateError = $_SESSION['migrate_error'];
    unset($_SESSION['migrate_error']);
}

try {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception('MongoDB dependencies are not installed yet.');
    }
    // MongoDB PHP library (https://www.mongodb.com/docs/php-library/current/)
    require_once $autoloadPath;

    $mongoHost = getenv('MONGO_HOST') ?: 'mongodb';
    $mongoPort = getenv('MONGO_PORT') ?: '27017';

    $mongo   = new MongoDB\Client("mongodb://$mongoHost:$mongoPort");
    $mongodb = $mongo->watchfolio_db;

    $migrationStatus = $mongodb->config->findOne(['_id' => 'migration_status']);
    if ($migrationStatus && !empty($migrationStatus['migrated'])) {
        $mongoStatus['migrated']    = true;
        $mongoStatus['migrated_at'] = $migrationStatus['migrated_at'] ?? null;
    }
} catch (Exception $e) {
    $mongoStatus['error'] = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $_SESSION['user_id']   = (int)$_POST['user_id'];
    $_SESSION['username']  = $_POST['username'];
}

$users         = [];
$latestReviews = [];

if (is_mongo_mode() && $mongodb !== null) {
    // ---- Query users from MongoDB ----
    $userCursor = $mongodb->app_user->find(
        [],
        ['sort' => ['username' => 1], 'projection' => ['_id' => 1, 'username' => 1]]
    );
    foreach ($userCursor as $u) {
        $users[] = ['user_id' => $u['_id'], 'username' => (string)$u['username']];
    }

    // ---- Query latest reviews from MongoDB via aggregation ----
    $pipeline = [
        ['$unwind' => '$reviews'],
        ['$match'  => ['reviews.review_number' => ['$lte' => 90]]],
        ['$sample' => ['size' => 4]],
        ['$project' => [
            'review_text' => '$reviews.review_text',
            'rating'      => '$reviews.rating',
            'username'    => '$reviews.username',
            'title'       => '$title',
            'content_id'  => '$_id',
        ]],
    ];
    foreach ($mongodb->content->aggregate($pipeline) as $r) {
        $latestReviews[] = [
            'review_text' => (string)$r['review_text'],
            'rating'      => (int)$r['rating'],
            'username'    => (string)$r['username'],
            'title'       => (string)$r['title'],
            'content_id'  => $r['content_id'],
        ];
    }
} else {
    // ---- Query from MariaDB ----
    $dbHost     = getenv('DB_HOST')     ?: 'mariadb';
    $dbUser     = getenv('DB_USER')     ?: 'watchfolio_user';
    $dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
    $dbName     = getenv('DB_NAME')     ?: 'watchfolio';

    // MariaDB via PHP mysqli extension (https://www.php.net/manual/en/book.mysqli.php)
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

    $userResult = $conn->query('SELECT user_id, username FROM app_user ORDER BY username');
    if ($userResult) {
        while ($u = $userResult->fetch_assoc()) {
            $users[] = $u;
        }
    }

    $reviewResult = $conn->query('
        SELECT r.review_text, r.rating, u.username, c.title, c.content_id
        FROM review r
        JOIN app_user u ON r.user_id = u.user_id
        JOIN content c ON r.content_id = c.content_id
        WHERE r.review_number <= 90
        ORDER BY RAND()
        LIMIT 4
    ');
    if ($reviewResult) {
        while ($row = $reviewResult->fetch_assoc()) {
            $latestReviews[] = $row;
        }
    }
}

// Fetch cover images from the images folder
$imageFiles = glob('images/*.*') ?: [];
$validImages = array_values(array_filter($imageFiles, function($file) {
    return preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
}));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Watchfolio</title>
    <style>
        :root { --primary: #ffaac7; --secondary: #e9c3d5; --accent: #f3c3cf; --danger: #ffc9e6; --bg: #fbd6ee; --card-bg: #ffffff; --text: #333333; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: var(--bg); color: var(--text); line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .card { background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .card h2 { margin-top: 0; color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; gap: 8px; }
        a { color: var(--primary); text-decoration: none; font-weight: 500; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--primary); color: #333; border-radius: 8px; margin-top: 12px; transition: all 0.2s ease; text-decoration: none; font-weight: 500; }
        .btn:hover { background: var(--secondary); color: #333; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-decoration: none; }
        .btn-red { background: var(--danger); }
        .btn-red:hover { background: var(--accent); }
        .review-text { font-style: italic; color: #475569; }
        .review-body { display: flex; gap: 20px; align-items: flex-start; margin-top: 15px; }
        .content-cover { width: 100px; height: 150px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); flex-shrink: 0; }
        .review-content { flex: 1; }
    </style>
    <link rel="stylesheet" href="assets/css/pixel-theme.css">
</head>
<body>
    <div class="user-bar">
        <span class="brand"><span class="pixel-symbol pixel-movie" aria-hidden="true"></span>Watchfolio</span>
        <form method="POST">
            <label style="color: #555; font-size: 0.95rem;">Acting as:</label>
            <select name="user_id" onchange="this.form.submit()">
                <option value="">-- Select User --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['user_id'] ?>"
                        data-username="<?= htmlspecialchars($user['username']) ?>"
                        <?= (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['user_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="username" id="username_hidden">
        </form>
        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-info"><span class="pixel-symbol pixel-user small" aria-hidden="true"></span><?= htmlspecialchars($_SESSION['username']) ?></div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="header">
            <h1>Welcome to Watchfolio</h1>
            <p>Your personal movie and TV show tracker</p>
        </div>

        <div class="grid">
            <div class="card">
                <h2><span class="pixel-symbol pixel-leaf" aria-hidden="true"></span>MongoDB Migration</h2>
                <p>Migrate all data from MariaDB to MongoDB. This will clear existing MongoDB data first.</p>
                <?php if ($migrateMessage): ?>
                    <p style="color: green;"><strong><?= htmlspecialchars($migrateMessage) ?></strong></p>
                <?php endif; ?>
                <?php if ($migrateError): ?>
                    <p style="color: red;"><strong>Error:</strong> <?= htmlspecialchars($migrateError) ?></p>
                <?php endif; ?>
                <?php if (!empty($mongoStatus['error'])): ?>
                    <p><strong>Status:</strong> MongoDB status could not be loaded.</p>
                <?php elseif (is_mongo_mode()): ?>
                    <p><strong>Status:</strong> Migrated (querying MongoDB)</p>
                    <?php if ($mongoStatus['migrated_at']): ?>
                        <p><strong>Migrated at:</strong> <?= htmlspecialchars($mongoStatus['migrated_at']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><strong>Status:</strong> Not migrated yet (querying MariaDB)</p>
                <?php endif; ?>
                <a href="migrate.php" class="btn"><span class="pixel-symbol pixel-rocket small" aria-hidden="true"></span>Migrate to MongoDB</a>
            </div>

            <div class="card">
                <h2><span class="pixel-symbol pixel-note" aria-hidden="true"></span>Use Cases</h2>
                <p>Manage your portfolio of watched movies and TV shows.</p>
                <?php if (is_mongo_mode()): ?>
                    <a href="add_movie_mongo.php" class="btn"><span class="pixel-symbol pixel-movie small" aria-hidden="true"></span>Add Movie MongoDB</a>
                    <a href="add_review_mongo.php" class="btn"><span class="pixel-symbol pixel-leaf small" aria-hidden="true"></span>Add Review MongoDB</a>
                    <a href="assign_actor_mongo.php" class="btn"><span class="pixel-symbol pixel-leaf small" aria-hidden="true"></span>Assign Actor MongoDB</a>
                <?php else: ?>
                    <a href="add_movie.php" class="btn"><span class="pixel-symbol pixel-movie small" aria-hidden="true"></span>Add New Movie</a>
                    <a href="add_review.php" class="btn"><span class="pixel-symbol pixel-note small" aria-hidden="true"></span>Add Review SQL</a>
                    <a href="assign_actor.php" class="btn"><span class="pixel-symbol pixel-user small" aria-hidden="true"></span>Assign Actor SQL</a>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2><span class="pixel-symbol pixel-gear" aria-hidden="true"></span>Data Setup</h2>
                <p>Reset and fill the database with randomized data.</p>
                <a href="generate_database.php" class="btn btn-red"><span class="pixel-symbol pixel-refresh small" aria-hidden="true"></span>Generate Data</a>
            </div>
        </div>

        <?php if (!empty($latestReviews)): ?>
        <div style="margin-top: 30px; margin-bottom: 10px;">
            <h2 style="color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; gap: 8px; margin: 0;"><span class="pixel-symbol pixel-star" aria-hidden="true"></span>Latest Reviews</h2>
        </div>
        <div class="grid">
            <?php foreach ($latestReviews as $review): ?>
                <?php
                $randomCover = null;
                if (!empty($validImages)) {
                    $imageIndex = $review['content_id'] % count($validImages);
                    $randomCover = $validImages[$imageIndex];
                }
                ?>
                <div class="card review-card">
                    <div class="review-body" style="margin-top: 0;">
                        <?php if ($randomCover): ?>
                            <img src="<?= htmlspecialchars($randomCover) ?>" alt="Cover for <?= htmlspecialchars($review['title']) ?>" class="content-cover">
                        <?php endif; ?>
                        <div class="review-content">
                            <div class="review-meta">
                                <span><strong><?= htmlspecialchars($review['title']) ?></strong><br>reviewed by <?= htmlspecialchars($review['username']) ?></span>
                                <span class="review-rating" aria-label="<?= (int)$review['rating'] ?> out of 5 hearts">
                                    <?php for ($heart = 1; $heart <= 5; $heart++): ?>
                                        <span class="pixel-heart <?= $heart > (int)$review['rating'] ? 'empty' : '' ?>" aria-hidden="true"></span>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <p class="review-text">"<?= htmlspecialchars($review['review_text']) ?>"</p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
<script src="assets/js/sparkle-cursor.js"></script>
</html>
