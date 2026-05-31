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

$sql = '
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
    (
        ?,
        ?,
        ?,
        ?,
        ?,
        ?,
        ?
    )
';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}

$insertedUsers = 0;

for ($i = 1; $i <= 50; $i++) {
    $username = 'user' . $i;
    $name = 'Test User ' . $i;
    $email = 'user' . $i . '@gmail.com';
    $birthYear = rand(1980, 2010);
    $gender = rand(0, 1) ? 'Male' : 'Female';
    $password = 'password123';

    $stmt->bind_param(
        'isssiss',
        $i,
        $username,
        $name,
        $email,
        $birthYear,
        $gender,
        $password
    );

    if ($stmt->execute()) {
        $insertedUsers++;
    } else {
        echo 'Insert failed for user ' . $i . ': ' . $stmt->error . '<br>';
    }
}

echo $insertedUsers . ' users inserted';

$stmt->close();
$conn->close();
