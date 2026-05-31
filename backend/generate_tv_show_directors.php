<?php

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Connected successfully<br>";

function getIds(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);

    if (!$result) {
        die('Query failed: ' . $conn->error);
    }

    $ids = [];

    while ($row = $result->fetch_row()) {
        $ids[] = (int) $row[0];
    }

    return $ids;
}

$tvShowIds = getIds($conn, 'SELECT content_id FROM tv_show ORDER BY content_id LIMIT 20');
$directorIds = getIds($conn, 'SELECT director_id FROM director ORDER BY director_id LIMIT 10');

if (count($tvShowIds) < 20 || count($directorIds) < 10) {
    die('Please generate 20 tv shows and 10 directors before generating tv show directors.');
}

$sql = '
    INSERT INTO tv_shows_directors
    (
        content_id,
        director_id
    )
    VALUES
    (
        ?,
        ?
    )
';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}

$insertedTvShowDirectors = 0;
$usedPairs = [];
$maxPairs = count($tvShowIds) * count($directorIds);
$targetRows = min(35, $maxPairs);

foreach ($tvShowIds as $contentId) {
    $directorId = $directorIds[array_rand($directorIds)];
    $pairKey = $contentId . '-' . $directorId;

    $usedPairs[$pairKey] = true;

    $stmt->bind_param(
        'ii',
        $contentId,
        $directorId
    );

    if ($stmt->execute()) {
        $insertedTvShowDirectors++;
    } else {
        echo 'Insert failed for tv show ' . $contentId . ' and director ' . $directorId . ': ' . $stmt->error . '<br>';
    }
}

while ($insertedTvShowDirectors < $targetRows) {
    $contentId = $tvShowIds[array_rand($tvShowIds)];
    $directorId = $directorIds[array_rand($directorIds)];
    $pairKey = $contentId . '-' . $directorId;

    if (isset($usedPairs[$pairKey])) {
        continue;
    }

    $usedPairs[$pairKey] = true;

    $stmt->bind_param(
        'ii',
        $contentId,
        $directorId
    );

    if ($stmt->execute()) {
        $insertedTvShowDirectors++;
    } else {
        echo 'Insert failed for tv show ' . $contentId . ' and director ' . $directorId . ': ' . $stmt->error . '<br>';
    }
}

echo $insertedTvShowDirectors . ' tv show directors inserted';

$stmt->close();
$conn->close();
