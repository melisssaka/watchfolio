<?php
// ============================================
// migrate.php – Migrates all data from MariaDB to MongoDB
// This script reads everything from our SQL database and writes it
// into MongoDB. We only do this once - after migration the app
// switches to MongoDB permanently and SQL is no longer used.
// sources that mostly used : 
//  https://www.mongodb.com/docs/php-library/current/
// https://www.mongodb.com/docs/manual/crud/
// https://www.mongodb.com/docs/manual/data-modeling/concepts/embedding-vs-references/
// ============================================
ob_start();
require_once __DIR__ . '/db_mode.php';

// 1. Connect to MariaDB 
// grabbing the connection details from environment variables
//source : https://www.php.net/manual/en/function.getenv.php
// (set in docker-compose.yml)
$dbHost     = getenv('DB_HOST')     ?: 'mariadb';
$dbUser     = getenv('DB_USER')     ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName     = getenv('DB_NAME')     ?: 'watchfolio';

$sql = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
if ($sql->connect_error) {    // if we can't connect to SQL there's nothing to migrate
    ob_end_clean();
    session_start();
    $_SESSION['migrate_error'] = 'MariaDB connection failed: ' . $sql->connect_error;
    header('Location: index.php');
    exit;
}

// 2. Connect to MongoDB 
require_once __DIR__ . '/vendor/autoload.php';

$mongoHost = getenv('MONGO_HOST') ?: 'mongodb';
$mongoPort = getenv('MONGO_PORT') ?: '27017';

$mongo = new MongoDB\Client("mongodb://$mongoHost:$mongoPort");
$db    = $mongo->watchfolio_db;

// 3. Clear all MongoDB collections first 
// we drop everything before migrating so we don't end up
// with duplicate data if someone clicks the migrate button twice
$db->app_user->drop();
$db->director->drop();
$db->actor->drop();
$db->content->drop();
$db->config->drop();


// 4. Migrate app_user
// straightforward - just copy all users over
// _id maps to user_id from SQL
$users    = $sql->query("SELECT * FROM app_user");
$userDocs = [];
while ($row = $users->fetch_assoc()) {
    $userDocs[] = [
        '_id'        => (int)$row['user_id'],
        'username'   => $row['username'],
        'name'       => $row['name'],
        'email'      => $row['email'],
        'birth_year' => (int)$row['birth_year'],
        'gender'     => $row['gender'],
        'password'   => $row['password'],
    ];
}
if (!empty($userDocs)) {
    $db->app_user->insertMany($userDocs);
}
$userCount = count($userDocs);


// 5. Migrate director 
// we also keep a $directorMap so we can look up directors
// by id later when building the content documents
$directors   = $sql->query("SELECT * FROM director");
$directorDocs = [];
$directorMap  = [];
while ($row = $directors->fetch_assoc()) {
    $doc = [
        '_id'         => (int)$row['director_id'],
        'name'        => $row['name'],
        'nationality' => $row['nationality'],
    ];
    $directorDocs[]                   = $doc;
    $directorMap[$row['director_id']] = $doc;
}
if (!empty($directorDocs)) {
    $db->director->insertMany($directorDocs);
}
$directorCount = count($directorDocs);


// 6. Migrate actor 
// same idea as directors - keep a map for later lookup
$actors   = $sql->query("SELECT * FROM actor");
$actorDocs = [];
$actorMap  = [];
while ($row = $actors->fetch_assoc()) {
    $doc = [
        '_id'           => (int)$row['actor_id'],
        'name'          => $row['name'],
        'num_of_awards' => (int)$row['num_of_awards'],
    ];
    $actorDocs[]                = $doc;
    $actorMap[$row['actor_id']] = $doc;
}
if (!empty($actorDocs)) {
    $db->actor->insertMany($actorDocs);
}
$actorCount = count($actorDocs);

// 7. Migrate content 
// in SQL we have separate tables for
// movie, tv_show, actor_content, tv_shows_directors, and review.
// in MongoDB we bring all of this together into one content document.
// so for each content item we need to:
//   - figure out if it's a movie or tv_show
//   - embed the director (for movies) or director_ids array (for tv shows)
//   - embed all assigned actors as an array
//   - embed all reviews as an array

$contents    = $sql->query("SELECT * FROM content");
$contentDocs = [];

while ($row = $contents->fetch_assoc()) {
    $contentId = $row['content_id'];
    // check if this content is a movie or tv show
    $movieResult = $sql->query("SELECT * FROM movie WHERE content_id = $contentId");
    $tvResult    = $sql->query("SELECT * FROM tv_show WHERE content_id = $contentId");

    $movie = $movieResult->fetch_assoc();
    $tv    = $tvResult->fetch_assoc();
    $type  = $movie ? 'movie' : 'tv_show';
    // build movie_details with embedded director
    // movies only have one director so we can embed directly   
    //source : https://www.mongodb.com/docs/manual/data-modeling/concepts/embedding-vs-references/
    $movieDetails = null;
    if ($movie) {
        $directorId   = $movie['director_id'];
        $director     = $directorMap[$directorId] ?? null;
        $movieDetails = [
            'duration'   => (int)$movie['duration'],
            'box_office' => (int)$movie['box_office'],
            'director'   => $director ? [
                'director_id' => (int)$director['_id'],
                'name'        => $director['name'],
                'nationality' => $director['nationality'],
            ] : null,
        ];
    }
     // build tv_show_details
    // tv shows can have multiple directors so we store director_ids as an array
    // instead of embedding the full director objects (to avoid duplication)
    // source : https://www.mongodb.com/docs/manual/data-modeling/concepts/embedding-vs-references/
    $tvDetails = null;
    if ($tv) {
        $tvDirectors = $sql->query(
            "SELECT director_id FROM tv_shows_directors WHERE content_id = $contentId"
        );
        $directorIds = [];
        while ($d = $tvDirectors->fetch_assoc()) {
            $directorIds[] = (int)$d['director_id'];
        }
        $tvDetails = [
            'num_episodes' => (int)$tv['num_episodes'],
            'num_seasons'  => (int)$tv['num_seasons'],
            'director_ids' => $directorIds,
        ];
    }
    // embed actors - in SQL this was a separate bridge table (actor_content)
    // in MongoDB we just push actor data directly into the content document
    $actorResult = $sql->query(
        "SELECT actor_id FROM actor_content WHERE content_id = $contentId"
    );
    $actors = [];
    while ($a = $actorResult->fetch_assoc()) {
        $actorId = $a['actor_id'];
        if (isset($actorMap[$actorId])) {
            $actors[] = [
                'actor_id'      => (int)$actorId,
                'name'          => $actorMap[$actorId]['name'],
                'num_of_awards' => $actorMap[$actorId]['num_of_awards'],
            ];
        }
    }
    // embed reviews - also denormalized into the content document
    // we join with app_user here so we have the username available
    // without needing to do a separate lookup later
    $reviewResult = $sql->query(
        "SELECT r.*, u.username
         FROM review r
         JOIN app_user u ON r.user_id = u.user_id
         WHERE r.content_id = $contentId"
    );
    $reviews = [];
    while ($r = $reviewResult->fetch_assoc()) {
        $reviews[] = [
            'review_number'        => (int)$r['review_number'],
            'user_id'              => (int)$r['user_id'],
            'username'             => $r['username'],
            'rating'               => (int)$r['rating'],
            'review_text'          => $r['review_text'],
            'parent_review_number' => $r['parent_review_number'] ? (int)$r['parent_review_number'] : null,
            'parent_user_id'       => $r['parent_user_id']       ? (int)$r['parent_user_id']       : null,
            'parent_content_id'    => $r['parent_content_id']    ? (int)$r['parent_content_id']    : null,
        ];
    }

    // put it all together into one content document
    // https://www.mongodb.com/docs/manual/data-modeling/
    $contentDocs[] = [
        '_id'             => (int)$contentId,
        'title'           => $row['title'],
        'genre'           => $row['genre'],
        'release_year'    => (int)$row['release_year'],
        'created_by_user' => $row['created_by_user'] ? (int)$row['created_by_user'] : null,
        'type'            => $type,
        'movie_details'   => $movieDetails,
        'tv_show_details' => $tvDetails,
        'actors'          => $actors,
        'reviews'         => $reviews,
    ];
}

if (!empty($contentDocs)) {
    $db->content->insertMany($contentDocs);
}
$contentCount = count($contentDocs);


//  8. Set migration flag 
// this flag is how the app knows migration is done
// db_config.php checks this on every request and blocks
// any further SQL access once it's set
$db->config->insertOne([
    '_id'         => 'migration_status',
    'migrated'    => true,
    'migrated_at' => date('Y-m-d H:i:s'),
]);

$sql->close();
set_db_mode('mongodb');
ob_end_clean();

// Redirect back with summary in session
session_start();
$_SESSION['migrate_success'] = "Migration complete: $userCount users, $directorCount directors, $actorCount actors, $contentCount content items moved to MongoDB.";
header('Location: index.php');
exit;
