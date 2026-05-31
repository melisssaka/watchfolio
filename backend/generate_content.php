<?php

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

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

for ($i = 1; $i <= 40; $i++) {
    $title = $word1[array_rand($word1)] . ' ' . $word2[array_rand($word2)];
    $genre = $genres[array_rand($genres)];
    $releaseYear = rand(1990, 2025);
    $createdByUser = rand(1, 20);

    $sql = "
        INSERT INTO content
        (
            content_id,
            title,
            genre,
            release_year,
            created_by_user
        )
        VALUES
        (
            $i,
            '$title',
            '$genre',
            $releaseYear,
            $createdByUser
        )
    ";

    if (!$conn->query($sql)) {
        die('Content insert failed: ' . $conn->error);
    }

    if ($i <= 20) {
        $duration = rand(80, 180);
        $boxOffice = rand(1000000, 1000000000);
        $directorId = rand(1, 10);

        $movieSql = "
            INSERT INTO movie
            (
                content_id,
                duration,
                box_office,
                director_id
            )
            VALUES
            (
                $i,
                $duration,
                $boxOffice,
                $directorId
            )
        ";

        if (!$conn->query($movieSql)) {
            die('Movie insert failed: ' . $conn->error);
        }
    } else {
        $numEpisodes = rand(6, 50);
        $numSeasons = rand(1, 8);

        $tvSql = "
            INSERT INTO tv_show
            (
                content_id,
                num_episodes,
                num_seasons
            )
            VALUES
            (
                $i,
                $numEpisodes,
                $numSeasons
            )
        ";

        if (!$conn->query($tvSql)) {
            die('TV Show insert failed: ' . $conn->error);
        }
    }
}

echo '<h2>Success!</h2>';
echo '40 content items generated.<br>';
echo '20 movies generated.<br>';
echo '20 TV shows generated.<br>';

$conn->close();
