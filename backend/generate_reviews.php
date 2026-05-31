<?php

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

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

    $stmt->bind_param(
        'iiiis',
        $reviewNumber,
        $userId,
        $contentId,
        $rating,
        $reviewText
    );

    if (!$stmt->execute()) {
        echo 'Review insert failed: ' . $stmt->error . '<br>';
        continue;
    }

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

    if (!$stmt->execute()) {
        echo 'Reply insert failed: ' . $stmt->error . '<br>';
        continue;
    }

    $stmt->close();
}

echo '<h2>Success!</h2>';
echo '100 reviews generated.<br>';
echo '90 normal reviews.<br>';
echo '10 reply reviews.<br>';

$conn->close();
