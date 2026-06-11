<?php
/*
 * PHP session management!
 * source: https://www.php.net/manual/en/function.session-start.php
 */
session_start();
/*
 * Database configuration loaded through environment variables.
 * source: https://www.php.net/manual/en/function.getenv.php
 */
$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';
# source https://www.php.net/manual/en/book.mysqli.php
$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$success = '';
$errors = [];

if (!isset($_SESSION['user_id'])) {
    $errors[] = 'Please select a user on the homepage first.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
    $contentId = (int) ($_POST['content_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $reviewText = trim($_POST['review_text'] ?? '');

    if ($contentId <= 0) {
        $errors[] = 'Please select a movie or TV show.';
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a rating between 1 and 5.';
    }

    if ($reviewText === '') {
        $errors[] = 'Please write a review.';
    }

    if (empty($errors)) {
        $nextReviewResult = $conn->query('SELECT COALESCE(MAX(review_number), 0) + 1 AS next_review_number FROM review');
        $nextReviewRow = $nextReviewResult->fetch_assoc();
        $reviewNumber = (int) $nextReviewRow['next_review_number'];
        #source https://www.php.net/manual/en/mysqli.quickstart.prepared-statements.php
        $stmt = $conn->prepare('
            INSERT INTO review
            (
                review_number,
                user_id,
                content_id,
                rating,
                review_text,
                parent_review_number,
                parent_user_id,
                parent_content_id
            )
            VALUES
            (?, ?, ?, ?, ?, NULL, NULL, NULL)
        ');

        $stmt->bind_param(
            'iiiis',
            $reviewNumber,
            $userId,
            $contentId,
            $rating,
            $reviewText
        );

        if ($stmt->execute()) {
            $success = 'Review created successfully.';
            $_POST = [];
        } else {
            $errors[] = 'Review insert failed: ' . $stmt->error;
        }

        $stmt->close();
    }
}

$contentItems = $conn->query('
    SELECT
        c.content_id,
        c.title,
        c.genre,
        c.release_year,
        CASE
            WHEN m.content_id IS NOT NULL THEN "Movie"
            WHEN tv.content_id IS NOT NULL THEN "TV Show"
            ELSE "Content"
        END AS content_type
    FROM content c
    LEFT JOIN movie m ON c.content_id = m.content_id
    LEFT JOIN tv_show tv ON c.content_id = tv.content_id
    ORDER BY content_type, c.title
');

$report = null;

if (isset($_SESSION['user_id'])) {
    $reportStmt = $conn->prepare('
        SELECT
            u.username,
            COUNT(r.review_number) AS num_of_reviews,
            SUM(m.content_id IS NOT NULL) AS num_of_movies_reviewed,
            SUM(tv.content_id IS NOT NULL) AS num_of_tv_shows_reviewed,
            CASE
                WHEN SUM(m.content_id IS NOT NULL) > SUM(tv.content_id IS NOT NULL)
                    THEN "This user prefers Movies!"
                WHEN SUM(m.content_id IS NOT NULL) < SUM(tv.content_id IS NOT NULL)
                    THEN "This user prefers TV Shows!"
                ELSE "This user has no preference, they watch movies and TV shows equally!"
            END AS watch_preference
        FROM review r
        JOIN app_user u ON r.user_id = u.user_id
        JOIN content c ON r.content_id = c.content_id
        LEFT JOIN movie m ON c.content_id = m.content_id
        LEFT JOIN tv_show tv ON c.content_id = tv.content_id
        WHERE u.user_id = ?
        GROUP BY u.user_id, u.username
    ');

    $reportStmt->bind_param('i', $_SESSION['user_id']);
    $reportStmt->execute();
    $report = $reportStmt->get_result()->fetch_assoc();
    $reportStmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Review - Watchfolio</title>
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
        select, textarea { width: 100%; padding: 10px; margin-top: 6px; border-radius: 8px; border: 1px solid var(--secondary); box-sizing: border-box; font-size: 15px; }
        textarea { min-height: 130px; resize: vertical; }
        .success { background: #e8ffe8; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .error { background: #ffe8ee; color: #9f1239; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; }
        th { background: var(--primary); }
        .header { text-align: center; margin: 40px 0 30px; }
        .header h1 { font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="user-bar">
        <span class="brand"><span class="pixel-symbol pixel-note" aria-hidden="true"></span>Add Review</span>

        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-info"><span class="pixel-symbol pixel-user small" aria-hidden="true"></span><?= htmlspecialchars($_SESSION['username']) ?></div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="header">
            <h1>Add Review</h1>
            <p>Create a movie or TV show review and see how it changes your viewing preference report.</p>
        </div>

        <div class="card">
            <h2><span class="pixel-symbol pixel-note" aria-hidden="true"></span>Create Review</h2>

            <?php foreach ($errors as $error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Movie or TV Show</label>
                <select name="content_id">
                    <option value="">-- Select content --</option>
                    <?php while ($content = $contentItems->fetch_assoc()): ?>
                        <option value="<?= (int) $content['content_id'] ?>" <?= (isset($_POST['content_id']) && (int) $_POST['content_id'] === (int) $content['content_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($content['content_type']) ?>:
                            <?= htmlspecialchars($content['title']) ?>
                            (<?= htmlspecialchars($content['genre']) ?>, <?= (int) $content['release_year'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Rating</label>
                <select name="rating">
                    <option value="">-- Select rating --</option>
                    <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                        <option value="<?= $rating ?>" <?= (isset($_POST['rating']) && (int) $_POST['rating'] === $rating) ? 'selected' : '' ?>>
                            <?= $rating ?> / 5
                        </option>
                    <?php endfor; ?>
                </select>

                <label>Review Text</label>
                <textarea name="review_text" placeholder="Write your review here..."><?= htmlspecialchars($_POST['review_text'] ?? '') ?></textarea>

                <button type="submit">
                    <span class="pixel-symbol pixel-star small" aria-hidden="true"></span>
                    Submit Review
                </button>
            </form>
        </div>

        <div class="card">
            <h2><span class="pixel-symbol pixel-star" aria-hidden="true"></span>Your Viewing Preference Report</h2>

            <?php if ($report): ?>
                <table>
                    <tr>
                        <th>User</th>
                        <th>Total Reviews</th>
                        <th>Movies Reviewed</th>
                        <th>TV Shows Reviewed</th>
                        <th>Preference</th>
                    </tr>
                    <tr>
                        <td><?= htmlspecialchars($report['username']) ?></td>
                        <td><?= (int) $report['num_of_reviews'] ?></td>
                        <td><?= (int) $report['num_of_movies_reviewed'] ?></td>
                        <td><?= (int) $report['num_of_tv_shows_reviewed'] ?></td>
                        <td><?= htmlspecialchars($report['watch_preference']) ?></td>
                    </tr>
                </table>
            <?php else: ?>
                <p>No reviews for this user yet. Add a review to generate the report.</p>
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
<?php
$conn->close();
