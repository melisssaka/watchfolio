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

$sql = '
    INSERT INTO actor
    (
        actor_id,
        name,
        num_of_awards
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

$insertedActors = 0;

for ($i = 1; $i <= 20; $i++) {
    $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    $numOfAwards = rand(0, 8);

    $stmt->bind_param(
        'isi',
        $i,
        $name,
        $numOfAwards
    );

    if ($stmt->execute()) {
        $insertedActors++;
    } else {
        echo 'Insert failed for actor ' . $i . ': ' . $stmt->error . '<br>';
    }
}

echo $insertedActors . ' actors inserted';

$stmt->close();
$conn->close();
