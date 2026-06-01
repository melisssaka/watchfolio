<?php
// ============================================
// migrate.php
// Migrates all data from MariaDB to MongoDB
// ============================================

// ---- 1. Connect to MariaDB ----
$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbUser = getenv('DB_USER') ?: 'watchfolio_user';
$dbPassword = getenv('DB_PASSWORD') ?: 'watchfolio_pass';
$dbName = getenv('DB_NAME') ?: 'watchfolio';

$sql = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
if ($sql->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'MariaDB connection failed: ' . $sql->connect_error
    ]));
}

// ---- 2. Connect to MongoDB ----
require_once __DIR__ . '/vendor/autoload.php';

$mongoHost = getenv('MONGO_HOST') ?: 'mongodb';
$mongoPort = getenv('MONGO_PORT') ?: '27017';

$mongo = new MongoDB\Client("mongodb://$mongoHost:$mongoPort");
$db = $mongo->watchfolio_db;

// ---- 3. Clear all MongoDB collections first ----
$db->app_user->drop();
$db->director->drop();
$db->actor->drop();
$db->content->drop();

echo "MongoDB cleared<br>";

// ============================================
// ---- 4. Migrate app_user ----
// ============================================
$users = $sql->query("SELECT * FROM app_user");
$userDocs = [];
while ($row = $users->fetch_assoc()) {
    $userDocs[] = [
        '_id'        => $row['user_id'],
        'username'   => $row['username'],
        'name'       => $row['name'],
        'email'      => $row['email'],
        'birth_year' => (int)$row['birth_year'],
        'gender'     => $row['gender'],
        'password'   => $row['password']
    ];
}
if (!empty($userDocs)) {
    $db->app_user->insertMany($userDocs);
}
echo count($userDocs) . " users migrated<br>";

// ============================================
// ---- 5. Migrate director ----
// ============================================
$directors = $sql->query("SELECT * FROM director");
$directorDocs = [];
$directorMap = []; // for quick lookup later
while ($row = $directors->fetch_assoc()) {
    $doc = [
        '_id'         => $row['director_id'],
        'name'        => $row['name'],
        'nationality' => $row['nationality']
    ];
    $directorDocs[] = $doc;
    $directorMap[$row['director_id']] = $doc;
}
if (!empty($directorDocs)) {
    $db->director->insertMany($directorDocs);
}
echo count($directorDocs) . " directors migrated<br>";

// ============================================
// ---- 6. Migrate actor ----
// ============================================
$actors = $sql->query("SELECT * FROM actor");
$actorDocs = [];
$actorMap = []; // for quick lookup later
while ($row = $actors->fetch_assoc()) {
    $doc = [
        '_id'          => $row['actor_id'],
        'name'         => $row['name'],
        'num_of_awards' => (int)$row['num_of_awards']
    ];
    $actorDocs[] = $doc;
    $actorMap[$row['actor_id']] = $doc;
}
if (!empty($actorDocs)) {
    $db->actor->insertMany($actorDocs);
}
echo count($actorDocs) . " actors migrated<br>";

// ============================================
// ---- 7. Migrate content (most complex) ----
// ============================================
$contents = $sql->query("SELECT * FROM content");
$contentDocs = [];

while ($row = $contents->fetch_assoc()) {
    $contentId = $row['content_id'];

    // -- Check if movie or tv_show --
    $movieResult = $sql->query(
        "SELECT * FROM movie WHERE content_id = $contentId"
    );
    $tvResult = $sql->query(
        "SELECT * FROM tv_show WHERE content_id = $contentId"
    );

    $movie = $movieResult->fetch_assoc();
    $tv    = $tvResult->fetch_assoc();
    $type  = $movie ? 'movie' : 'tv_show';

    // -- Build movie_details --
    $movieDetails = null;
    if ($movie) {
        $directorId = $movie['director_id'];
        $director   = isset($directorMap[$directorId]) ? $directorMap[$directorId] : null;
        $movieDetails = [
            'duration'   => (int)$movie['duration'],
            'box_office' => (int)$movie['box_office'],
            'director'   => $director ? [
                'director_id' => $director['_id'],
                'name'        => $director['name'],
                'nationality' => $director['nationality']
            ] : null
        ];
    }

    // -- Build tv_show_details --
    $tvDetails = null;
    if ($tv) {
        // Get director_ids from tv_shows_directors
        $tvDirectors = $sql->query(
            "SELECT director_id FROM tv_shows_directors 
             WHERE content_id = $contentId"
        );
        $directorIds = [];
        while ($d = $tvDirectors->fetch_assoc()) {
            $directorIds[] = (int)$d['director_id'];
        }
        $tvDetails = [
            'num_episodes' => (int)$tv['num_episodes'],
            'num_seasons'  => (int)$tv['num_seasons'],
            'director_ids' => $directorIds
        ];
    }

    // -- Build actors array --
    $actorResult = $sql->query(
        "SELECT actor_id FROM actor_content 
         WHERE content_id = $contentId"
    );
    $actors = [];
    while ($a = $actorResult->fetch_assoc()) {
        $actorId = $a['actor_id'];
        if (isset($actorMap[$actorId])) {
            $actors[] = [
                'actor_id'      => $actorId,
                'name'          => $actorMap[$actorId]['name'],
                'num_of_awards' => $actorMap[$actorId]['num_of_awards']
            ];
        }
    }

    // -- Build reviews array --
    $reviewResult = $sql->query(
        "SELECT r.*, u.username 
         FROM review r
         JOIN app_user u ON r.user_id = u.user_id
         WHERE r.content_id = $contentId"
    );
    $reviews = [];
    while ($r = $reviewResult->fetch_assoc()) {
        $reviews[] = [
            'review_number'       => (int)$r['review_number'],
            'user_id'             => (int)$r['user_id'],
            'username'            => $r['username'],
            'rating'              => (int)$r['rating'],
            'review_text'         => $r['review_text'],
            'parent_review_number'=> $r['parent_review_number'] 
                                        ? (int)$r['parent_review_number'] 
                                        : null,
            'parent_user_id'      => $r['parent_user_id'] 
                                        ? (int)$r['parent_user_id'] 
                                        : null,
            'parent_content_id'   => $r['parent_content_id'] 
                                        ? (int)$r['parent_content_id'] 
                                        : null
        ];
    }

    // -- Build final content document --
    $contentDocs[] = [
        '_id'             => $contentId,
        'title'           => $row['title'],
        'genre'           => $row['genre'],
        'release_year'    => (int)$row['release_year'],
        'created_by_user' => $row['created_by_user'] 
                                ? (int)$row['created_by_user'] 
                                : null,
        'type'            => $type,
        'movie_details'   => $movieDetails,
        'tv_show_details' => $tvDetails,
        'actors'          => $actors,
        'reviews'         => $reviews
    ];
}

if (!empty($contentDocs)) {
    $db->content->insertMany($contentDocs);
}
echo count($contentDocs) . " content items migrated<br>";

// ============================================
// ---- 8. Set migration flag in MongoDB ----
// ============================================
$db->config->drop();
$db->config->insertOne([
    '_id' => 'migration_status',
    'migrated' => true,
    'migrated_at' => date('Y-m-d H:i:s')
]);

// ---- 9. Done ----
$sql->close();

echo "<br><strong>Migration complete! 
      MariaDB connection closed. 
      Now using MongoDB only.</strong>";
