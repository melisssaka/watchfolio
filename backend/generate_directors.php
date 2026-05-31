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

$sql = '
    INSERT INTO director
    (
        director_id,
        name,
        nationality
    )
    VALUES
    (
        ?,
        ?,
        ?
    )
';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}

$insertedDirectors = 0;

for ($i = 1; $i <= 10; $i++) {
    $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    $nationality = $nationalities[array_rand($nationalities)];

    $stmt->bind_param(
        'iss',
        $i,
        $name,
        $nationality
    );

    if ($stmt->execute()) {
        $insertedDirectors++;
    } else {
        echo 'Insert failed for director ' . $i . ': ' . $stmt->error . '<br>';
    }
}

echo $insertedDirectors . ' directors inserted';

$stmt->close();
$conn->close();
