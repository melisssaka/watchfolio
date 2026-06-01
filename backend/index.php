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

// Fetch the latest reviews
$latestReviewsQuery = $conn->query('
    SELECT r.review_text, r.rating, u.username, c.title, c.content_id
    FROM review r
    JOIN app_user u ON r.user_id = u.user_id
    JOIN content c ON r.content_id = c.content_id
    WHERE r.review_number <= 90
    ORDER BY RAND()
    LIMIT 4
');
$latestReviews = [];
if ($latestReviewsQuery) {
    while ($row = $latestReviewsQuery->fetch_assoc()) {
        $latestReviews[] = $row;
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
        .user-bar { background: var(--primary); color: #333; padding: 15px 30px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .user-bar span.brand { font-size: 1.25rem; font-weight: bold; display: flex; align-items: center; gap: 8px; }
        .user-bar form { display: flex; align-items: center; gap: 15px; margin: 0; flex-grow: 1; }
        .user-bar select { padding: 8px 12px; border-radius: 6px; border: 1px solid var(--secondary); background: #fff; font-size: 14px; color: #333; cursor: pointer; }
        .user-info { display: flex; align-items: center; gap: 8px; font-weight: 500; background: rgba(255,255,255,0.5); padding: 6px 12px; border-radius: 20px; }
        .header { text-align: center; margin: 40px 0 30px; }
        .header h1 { font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; }
        .header p { color: #64748b; font-size: 1.1rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .review-card { border-left: 4px solid var(--accent); }
        .review-meta { display: flex; flex-direction: column; gap: 5px; margin-bottom: 10px; font-size: 0.9rem; color: #64748b; }
        .review-rating { color: #ff748c; font-weight: bold; font-size: 1.2rem; letter-spacing: 2px; }
        .review-text { font-style: italic; color: #475569; }
        .review-body { display: flex; gap: 20px; align-items: flex-start; margin-top: 15px; }
        .content-cover { width: 100px; height: 150px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); flex-shrink: 0; }
        .review-content { flex: 1; }
    </style>
</head>
<body>
    <div class="user-bar">
        <span class="brand">🎬 Watchfolio</span>
        <form method="POST">
            <label style="color: #555; font-size: 0.95rem;">Acting as:</label>
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
            <div class="user-info">👤 <?= htmlspecialchars($_SESSION['username']) ?></div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="header">
            <h1>Welcome to Watchfolio</h1>
            <p>Your personal movie and TV show tracker</p>
        </div>

        <div class="grid">
            <div class="card">
                <h2>🍃 MongoDB Migration</h2>
                <p>Migrate all data from MariaDB to MongoDB. This will clear existing MongoDB data first.</p>
                <a href="migrate.php" class="btn">🚀 Migrate to MongoDB</a>
            </div>

            <div class="card">
                <h2>📝 Use Cases</h2>
                <p>Manage your portfolio of watched movies and TV shows.</p>
                <a href="add_movie.php" class="btn">🎬 Add New Movie</a>
            </div>

            <div class="card">
                <h2>⚙️ Data Setup</h2>
                <p>Reset and fill the database with randomized data.</p>
                <a href="generate_database.php" class="btn btn-red">🔄 Generate Data</a>
            </div>
        </div>

        <?php if (!empty($latestReviews)): ?>
        <div style="margin-top: 30px; margin-bottom: 10px;">
            <h2 style="color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; gap: 8px; margin: 0;">⭐ Latest Reviews</h2>
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
                                <span class="review-rating"><?= str_repeat('★', $review['rating']) ?><span style="color: #e2e8f0;"><?= str_repeat('★', 5 - $review['rating']) ?></span></span>
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
</html>