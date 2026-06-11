<?php
# source https://www.php.net/manual/en/function.getenv.php
$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';
#source: https://www.php.net/manual/en/book.mysqli.php
$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function runQuery(mysqli $conn, string $sql, string $message): void
{
    if (!$conn->query($sql)) {
        renderResultPage(
            'Database generation failed',
            [
                $message . ': ' . $conn->error
            ],
            true
        );
        exit;
    }
}

function renderResultPage(string $title, array $messages, bool $isError = false): void
{
    $iconClass = $isError ? 'pixel-gear' : 'pixel-heart';
    $cardClass = $isError ? 'result-card error-card' : 'result-card';
    ?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($title) ?> - Watchfolio</title>
    <link rel="stylesheet" href="assets/css/pixel-theme.css">
    <style>
        :root { --primary: #ffaac7; --secondary: #e9c3d5; --accent: #f3c3cf; --danger: #ffc9e6; --bg: #fbd6ee; --card-bg: #ffffff; --text: #333333; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: var(--bg); color: var(--text); line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .card { background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .card h1 { margin-top: 0; color: var(--primary); font-size: 2rem; display: flex; align-items: center; gap: 10px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--primary); color: #333; border-radius: 8px; margin-top: 18px; transition: all 0.2s ease; text-decoration: none; font-weight: 500; }
        .btn:hover { background: var(--secondary); color: #333; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-decoration: none; }
        .result-card { border-left: 6px solid var(--primary); }
        .error-card { border-left-color: var(--danger); }
        .result-list { margin: 18px 0 0; padding: 0; list-style: none; }
        .result-list li { align-items: center; display: flex; gap: 10px; margin: 8px 0; }
    </style>
</head>
<body>
    <div class="user-bar">
        <span class="brand"><span class="pixel-symbol pixel-refresh" aria-hidden="true"></span>Watchfolio</span>
    </div>

    <div class="container">
        <div class="<?= $cardClass ?>">
            <h1><span class="pixel-symbol <?= $iconClass ?>" aria-hidden="true"></span><?= htmlspecialchars($title) ?></h1>
            <ul class="result-list">
                <?php foreach ($messages as $message): ?>
                    <li><span class="pixel-heart" aria-hidden="true"></span><?= htmlspecialchars($message) ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="index.php" class="btn"><span class="pixel-symbol pixel-movie small" aria-hidden="true"></span>Return to Homepage</a>
        </div>
    </div>

    <script src="assets/js/sparkle-cursor.js"></script>
</body>
</html>
    <?php
}

function randomName(array $firstNames, array $lastNames): string
{
    return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
}

$firstNames = [
    'Charlie',
    'Julia',
    'Flo',
    'Ivan',
    'Marta',
    'Nina',
    'Daniel',
    'Lucas',
    'Noah',
    'Mia',
    'Maja',
    'Julian',
    'Pete',
    'Markus'
];

$lastNames = [
    'Smith',
    'Johnson',
    'Gruber',
    'Huber',
    'Bauer',
    'Stone',
    'Anderson',
    'Moore',
    'Roberts',
    'King',
    'Mayer',
    'Neumann'
];

$genders = [
    'Male',
    'Female'
];

$nationalities = [
    'American',
    'British',
    'German',
    'French',
    'Italian',
    'Canadian',
    'Spanish',
    'Japanese',
    'Australian',
    'Swedish'
];

$genres = [
    'Action',
    'Drama',
    'Comedy',
    'Sci-Fi',
    'Romance',
    'Fantasy'
];

$word1 = [
    'Dark',
    'Lost',
    'Magical',
    'Supernatural',
    'Silent',
    'Golden',
    'Broken',
    'Last',
    'First',
    'Best',
    'Cutest',
    'Special',
    'Secret',
    'Ancient'
];

$word2 = [
    'Journey',
    'World',
    'Legend',
    'Friends',
    'Twins',
    'Truth',
    'Shadow',
    'Night',
    'Dream',
    'Kingdom',
    'Princess',
    'City',
    'Memory',
    'River'
];

$reviewTexts = [
    'I love it wow!',
    'Really enjoyed it!',
    'Media used to be better in the 70s, this was pretty bad!',
    'Could have been better.....really disappointed with this!',
    'Fantastic acting!',
    'Great story',
    'Very disappointing, usually I love these actors but this was so bad!',
    'Loved every minute, a must watch!',
    'Highly recommended, perfect for a lazy night in',
    "Worth watching, wouldn't even let my enemy suffer through this",
    'Excellent casting, bad storywriting',
    'Very entertaining, but nothing groundbreaking....',
    'Interesting plot, never seen something like this before',
    'Would watch again, will go in my yearly binge rotation.',
    'Not my favorite :/',
    'Pretty good overall, would not watch again tho.',
    'Great visuals but the story was awful! Who wrote this script? Ugh!',
    'Weak ending, I predicted it in the first 10 minutes, so lame',
    'Surprisingly good, I was going into this expecting a hate-watch, pleasantly surprised',
    "Average experience, can't believe it's 2026 and mediocre stuff like this still gets produced bruh"
];

$reviewReplyTexts = [
    "I don't agree with you at all.",
    'I totally agree with you.',
    'Even though I disagree with you, I can see where you are coming from.',
    'Idk....maybe you have a point.',
    'I personally loveee this one! It was sooo good!',
    "Nah, you're being way too harsh.",
    'Honestly this review convinced me to watch it.',
    "That's exactly how I felt after watching it.",
    'I think you completely missed the point of the story.',
    'Fair take, but I still enjoyed it.',
    'Strongly disagree, this was one of the best releases this year.',
    'Finally someone said it!',
    'You are absolutely right about the ending.',
    'The acting carried the entire thing for me.',
    'I think nostalgia is influencing your opinion a bit.'
];

// If MongoDB mode: clear all MongoDB data and reset flag to SQL
#source https://www.mongodb.com/docs/php-library/current/ 
require_once __DIR__ . '/db_mode.php';
if (is_mongo_mode()) {
    require_once __DIR__ . '/vendor/autoload.php';
    try {
        $mongoHost = getenv('MONGO_HOST') ?: 'mongodb';
        $mongoPort = getenv('MONGO_PORT') ?: '27017';
        $mongoClient = new MongoDB\Client("mongodb://$mongoHost:$mongoPort");
        $mongoDb = $mongoClient->watchfolio_db;
        $mongoDb->app_user->drop();
        $mongoDb->director->drop();
        $mongoDb->actor->drop();
        $mongoDb->content->drop();
        $mongoDb->config->drop();
    } catch (Exception $e) {
        // Non-fatal: continue and reset flag regardless
    }
    set_db_mode('sql');
}
#sources https://mariadb.com/docs/server/architecture/server-constraints/foreign-key-constraints 
runQuery($conn, 'SET FOREIGN_KEY_CHECKS = 0', 'Could not disable foreign key checks');
runQuery($conn, 'TRUNCATE TABLE actor_content', 'Could not empty actor_content');
runQuery($conn, 'TRUNCATE TABLE tv_shows_directors', 'Could not empty tv_shows_directors');
runQuery($conn, 'TRUNCATE TABLE review', 'Could not empty review');
runQuery($conn, 'TRUNCATE TABLE movie', 'Could not empty movie');
runQuery($conn, 'TRUNCATE TABLE tv_show', 'Could not empty tv_show');
runQuery($conn, 'TRUNCATE TABLE content', 'Could not empty content');
runQuery($conn, 'TRUNCATE TABLE actor', 'Could not empty actor');
runQuery($conn, 'TRUNCATE TABLE director', 'Could not empty director');
runQuery($conn, 'TRUNCATE TABLE app_user', 'Could not empty app_user');
runQuery($conn, 'SET FOREIGN_KEY_CHECKS = 1', 'Could not enable foreign key checks');

for ($i = 1; $i <= 20; $i++) {
    $username = 'username' . $i;
    $name = randomName($firstNames, $lastNames);
    $email = $username . '@gmail.com';
    $birthYear = rand(1980, 2010);
    $gender = $genders[array_rand($genders)];
    $password = 'password123';
    /*
     * SQL queries are executed using prepared statements
     * to safely bind parameters and avoid SQL injection.
     *
     * Source:
     * https://www.php.net/manual/en/mysqli.quickstart.prepared-statements.php
     */
    $stmt = $conn->prepare("
        INSERT INTO app_user
        (
            user_id,
            username,
            name,
            email,
            birth_year,
            gender,
            password
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?)
    ");
    /*
     * Parameters are bound using MySQLi prepared statements.
     * Source:
     * https://www.php.net/manual/en/mysqli-stmt.bind-param.php
    */
    $stmt->bind_param('isssiss', $i, $username, $name, $email, $birthYear, $gender, $password);
    $stmt->execute();
    $stmt->close();
}

for ($i = 1; $i <= 10; $i++) {
    $name = randomName($firstNames, $lastNames);
    $nationality = $nationalities[array_rand($nationalities)];

    $stmt = $conn->prepare("
        INSERT INTO director
        (
            director_id,
            name,
            nationality
        )
        VALUES
        (?, ?, ?)
    ");

    $stmt->bind_param('iss', $i, $name, $nationality);
    $stmt->execute();
    $stmt->close();
}

for ($i = 1; $i <= 20; $i++) {
    $name = randomName($firstNames, $lastNames);
    $numOfAwards = rand(0, 8);

    $stmt = $conn->prepare("
        INSERT INTO actor
        (
            actor_id,
            name,
            num_of_awards
        )
        VALUES
        (?, ?, ?)
    ");

    $stmt->bind_param('isi', $i, $name, $numOfAwards);
    $stmt->execute();
    $stmt->close();
}

for ($i = 1; $i <= 40; $i++) {
    $title = $word1[array_rand($word1)] . ' ' . $word2[array_rand($word2)];
    $genre = $genres[array_rand($genres)];
    $releaseYear = rand(1990, 2025);
    $createdByUser = rand(1, 20);

    $stmt = $conn->prepare("
        INSERT INTO content
        (
            content_id,
            title,
            genre,
            release_year,
            created_by_user
        )
        VALUES
        (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param('issii', $i, $title, $genre, $releaseYear, $createdByUser);
    $stmt->execute();
    $stmt->close();

    if ($i <= 20) {
        $duration = rand(80, 180);
        $boxOffice = rand(1000000, 1000000000);
        $directorId = rand(1, 10);

        $stmt = $conn->prepare("
            INSERT INTO movie
            (
                content_id,
                duration,
                box_office,
                director_id
            )
            VALUES
            (?, ?, ?, ?)
        ");

        $stmt->bind_param('iiii', $i, $duration, $boxOffice, $directorId);
        $stmt->execute();
        $stmt->close();
    } else {
        $numEpisodes = rand(6, 50);
        $numSeasons = rand(1, 8);

        $stmt = $conn->prepare("
            INSERT INTO tv_show
            (
                content_id,
                num_episodes,
                num_seasons
            )
            VALUES
            (?, ?, ?)
        ");

        $stmt->bind_param('iii', $i, $numEpisodes, $numSeasons);
        $stmt->execute();
        $stmt->close();
    }
}

$generatedActorContent = 0;

while ($generatedActorContent < 40) {
    $actorId = rand(1, 20);
    $contentId = rand(1, 40);
    #source https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/inserting-loading-data/insert-ignore
    $stmt = $conn->prepare("
        INSERT IGNORE INTO actor_content
        (
            actor_id,
            content_id
        )
        VALUES
        (?, ?)
    ");

    $stmt->bind_param('ii', $actorId, $contentId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $generatedActorContent++;
    }

    $stmt->close();
}

$generatedTvShowDirectors = 0;
$usedPairs = [];

for ($contentId = 21; $contentId <= 40; $contentId++) {
    $directorId = rand(1, 10);
    $pairKey = $contentId . '-' . $directorId;
    $usedPairs[$pairKey] = true;

    $stmt = $conn->prepare("
        INSERT INTO tv_shows_directors
        (
            content_id,
            director_id
        )
        VALUES
        (?, ?)
    ");

    $stmt->bind_param('ii', $contentId, $directorId);
    $stmt->execute();
    $stmt->close();
    $generatedTvShowDirectors++;
}

while ($generatedTvShowDirectors < 35) {
    $contentId = rand(21, 40);
    $directorId = rand(1, 10);
    $pairKey = $contentId . '-' . $directorId;

    if (isset($usedPairs[$pairKey])) {
        continue;
    }

    $usedPairs[$pairKey] = true;

    $stmt = $conn->prepare("
        INSERT INTO tv_shows_directors
        (
            content_id,
            director_id
        )
        VALUES
        (?, ?)
    ");

    $stmt->bind_param('ii', $contentId, $directorId);
    $stmt->execute();
    $stmt->close();
    $generatedTvShowDirectors++;
}

$existingReviews = [];
#source https://www.php.net/manual/en/function.rand.php
/*
 * Self-referencing review relationship based on MariaDB foreign key design:
 * https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/foreign-keys
 */

for ($i = 1; $i <= 90; $i++) {
    $reviewNumber = $i;
    $userId = rand(1, 20);
    $contentId = rand(1, 40);
    $rating = rand(1, 5);
    #source https://www.w3schools.com/PHP/func_array_rand.asp and https://www.php.net/manual/en/function.array-rand.php
    $reviewText = $reviewTexts[array_rand($reviewTexts)];

    $stmt = $conn->prepare("
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
    ");

    $stmt->bind_param('iiiis', $reviewNumber, $userId, $contentId, $rating, $reviewText);
    $stmt->execute();

    $existingReviews[] = [
        'review_number' => $reviewNumber,
        'user_id' => $userId,
        'content_id' => $contentId
    ];

    $stmt->close();
}

for ($i = 91; $i <= 100; $i++) {
    $reviewNumber = $i;
    $parent = $existingReviews[array_rand($existingReviews)];
    $parentReviewNumber = $parent['review_number'];
    $parentUserId = $parent['user_id'];
    $parentContentId = $parent['content_id'];
    $contentId = $parentContentId;

    do {
        $userId = rand(1, 20);
    } while ($userId == $parentUserId);

    $rating = rand(1, 5);
    $reviewText = $reviewReplyTexts[array_rand($reviewReplyTexts)];

    $stmt = $conn->prepare("
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
        (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'iiiisiii',
        $reviewNumber,
        $userId,
        $contentId,
        $rating,
        $reviewText,
        $parentReviewNumber,
        $parentUserId,
        $parentContentId
    );

    $stmt->execute();
    $stmt->close();
}

renderResultPage(
    'Success!',
    [
        'Existing SQL data deleted.',
        '20 users generated.',
        '10 directors generated.',
        '20 actors generated.',
        '40 content items generated.',
        '20 movies generated.',
        '20 TV shows generated.',
        '100 reviews generated.',
        '40 actor-content relationships generated.',
        '35 TV show-director relationships generated.'
    ]
);

$conn->close();
