<?php

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$generated = 0;

while ($generated < 40) {
    $actorId = rand(1, 20);
    $contentId = rand(1, 40);

    $stmt = $conn->prepare("
        INSERT IGNORE INTO actor_content
        (
            actor_id,
            content_id
        )
        VALUES
        (
            ?,
            ?
        )
    ");

    $stmt->bind_param(
        'ii',
        $actorId,
        $contentId
    );

    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $generated++;
    }

    $stmt->close();
}

echo '<h2>Success!</h2>';
echo '40 actor-content relationships generated.';

$conn->close();
