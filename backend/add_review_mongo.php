<?php
session_start();

$success = '';
$errors = [];
$contentItems = [];
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

    $migrationStatus = $mongodb->config->findOne([
        '_id' => 'migration_status'
    ]);

    $isMigrated = $migrationStatus && !empty($migrationStatus['migrated']);

    if (!$isMigrated) {
        $errors[] = 'Please migrate SQL data to MongoDB before using the MongoDB review page.';
    }
} catch (Exception $e) {
    $errors[] = 'MongoDB connection failed: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $isMigrated && $mongodb) {
    $userId = (int) $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? ('username' . $userId);
    $contentId = trim($_POST['content_id'] ?? '');
    $rating = (int) ($_POST['rating'] ?? 0);
    $reviewText = trim($_POST['review_text'] ?? '');

    if ($contentId === '') {
        $errors[] = 'Please select a movie or TV show.';
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a rating between 1 and 5.';
    }

    if ($reviewText === '') {
        $errors[] = 'Please write a review.';
    }

    if (empty($errors)) {
        $maxReviewResult = $mongodb->content->aggregate([
            [
                '$unwind' => [
                    'path' => '$reviews',
                    'preserveNullAndEmptyArrays' => false
                ]
            ],
            [
                '$group' => [
                    '_id' => null,
                    'max_review_number' => [
                        '$max' => '$reviews.review_number'
                    ]
                ]
            ]
        ])->toArray();

        $reviewNumber = 1;

        if (!empty($maxReviewResult) && isset($maxReviewResult[0]['max_review_number'])) {
            $reviewNumber = ((int) $maxReviewResult[0]['max_review_number']) + 1;
        }

        $updateResult = $mongodb->content->updateOne(
            [
                '_id' => [
                    '$in' => [
                        $contentId,
                        (int) $contentId
                    ]
                ]
            ],
            [
                '$push' => [
                    'reviews' => [
                        'review_number' => $reviewNumber,
                        'user_id' => $userId,
                        'username' => $username,
                        'rating' => $rating,
                        'review_text' => $reviewText,
                        'parent_review_number' => null,
                        'parent_user_id' => null,
                        'parent_content_id' => null
                    ]
                ]
            ]
        );

        if ($updateResult->getModifiedCount() > 0) {
            $success = 'Review created successfully in MongoDB.';
            $_POST = [];
        } else {
            $errors[] = 'Review insert failed: selected content was not found in MongoDB.';
        }
    }
}

if ($isMigrated && $mongodb) {
    $contentItems = $mongodb->content->find(
        [],
        [
            'sort' => [
                'type' => 1,
                'title' => 1
            ],
            'projection' => [
                '_id' => 1,
                'title' => 1,
                'type' => 1,
                'genre' => 1,
                'release_year' => 1
            ]
        ]
    );

    if (isset($_SESSION['user_id'])) {
        $reportResult = $mongodb->content->aggregate([
            [
                '$unwind' => [
                    'path' => '$reviews',
                    'preserveNullAndEmptyArrays' => false
                ]
            ],
            [
                '$match' => [
                    'reviews.user_id' => (int) $_SESSION['user_id']
                ]
            ],
            [
                '$group' => [
                    '_id' => '$reviews.user_id',
                    'username' => [
                        '$first' => '$reviews.username'
                    ],
                    'num_of_reviews' => [
                        '$sum' => 1
                    ],
                    'num_of_movies_reviewed' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$eq' => [
                                        '$type',
                                        'movie'
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ],
                    'num_of_tv_shows_reviewed' => [
                        '$sum' => [
                            '$cond' => [
                                [
                                    '$eq' => [
                                        '$type',
                                        'tv_show'
                                    ]
                                ],
                                1,
                                0
                            ]
                        ]
                    ]
                ]
            ],
            [
                '$addFields' => [
                    'watch_preference' => [
                        '$switch' => [
                            'branches' => [
                                [
                                    'case' => [
                                        '$gt' => [
                                            '$num_of_movies_reviewed',
                                            '$num_of_tv_shows_reviewed'
                                        ]
                                    ],
                                    'then' => 'This user prefers Movies!'
                                ],
                                [
                                    'case' => [
                                        '$lt' => [
                                            '$num_of_movies_reviewed',
                                            '$num_of_tv_shows_reviewed'
                                        ]
                                    ],
                                    'then' => 'This user prefers TV Shows!'
                                ]
                            ],
                            'default' => 'This user has no preference, they watch movies and TV shows equally!'
                        ]
                    ]
                ]
            ]
        ])->toArray();

        if (!empty($reportResult)) {
            $report = $reportResult[0];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Review MongoDB - Watchfolio</title>
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
        <span class="brand"><span class="pixel-symbol pixel-leaf" aria-hidden="true"></span>Add Review MongoDB</span>

        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-info"><span class="pixel-symbol pixel-user small" aria-hidden="true"></span><?= htmlspecialchars($_SESSION['username']) ?></div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="header">
            <h1>Add Review MongoDB</h1>
            <p>Create a movie or TV show review in MongoDB and see how it changes your viewing preference report.</p>
        </div>

        <div class="card">
            <h2><span class="pixel-symbol pixel-note" aria-hidden="true"></span>Create Review</h2>

            <?php foreach ($errors as $error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!$isMigrated): ?>
                <a href="migrate.php" class="btn">
                    <span class="pixel-symbol pixel-rocket small" aria-hidden="true"></span>
                    Migrate SQL Data to MongoDB
                </a>
            <?php else: ?>
                <form method="POST">
                    <label>Movie or TV Show</label>
                    <select name="content_id">
                        <option value="">-- Select content --</option>
                        <?php foreach ($contentItems as $content): ?>
                            <option value="<?= htmlspecialchars((string) $content['_id']) ?>" <?= (isset($_POST['content_id']) && (string) $_POST['content_id'] === (string) $content['_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($content['type']) ?>:
                                <?= htmlspecialchars($content['title']) ?>
                                (<?= htmlspecialchars($content['genre']) ?>, <?= (int) $content['release_year'] ?>)
                            </option>
                        <?php endforeach; ?>
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
            <?php endif; ?>
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
            <?php elseif ($isMigrated): ?>
                <p>No reviews for this user yet. Add a review to generate the report.</p>
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
