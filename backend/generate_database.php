<?php

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function runQuery(mysqli $conn, string $sql, string $message): void
{
    if (!$conn->query($sql)) {
        die($message . ': ' . $conn->error);
    }
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

for ($i = 1; $i <= 90; $i++) {
    $reviewNumber = $i;
    $userId = rand(1, 20);
    $contentId = rand(1, 40);
    $rating = rand(1, 5);
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

echo '<h2>Success!</h2>';
echo 'Existing SQL data deleted.<br>';
echo '20 users generated.<br>';
echo '10 directors generated.<br>';
echo '20 actors generated.<br>';
echo '40 content items generated.<br>';
echo '20 movies generated.<br>';
echo '20 TV shows generated.<br>';
echo '100 reviews generated.<br>';
echo '40 actor-content relationships generated.<br>';
echo '35 TV show-director relationships generated.<br>';

$conn->close();
