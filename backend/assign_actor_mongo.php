<?php
session_start();
// initialize everything to empty/false so the page
// doesn't crash if MongoDB isn't connected yet
$success    = '';
$errors     = [];
$actors     = [];
$content    = [];
$report     = [];
$genres     = [];
$actors_filter = [];
$isMigrated = false;
$mongodb    = null;

    // check if composer dependencies are installed
    // this was a problem during development so I handle it explicitly
try {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new Exception('MongoDB dependencies are not installed yet.');
    }
    require_once $autoloadPath;

    // get MongoDB connection details from docker-compose environment variables
    $mongoHost = getenv('MONGO_HOST') ?: 'mongodb';
    $mongoPort = getenv('MONGO_PORT') ?: '27017';

    $mongo   = new MongoDB\Client("mongodb://$mongoHost:$mongoPort");
    $mongodb = $mongo->watchfolio_db;

    // check the config collection to see if migration has been done
    // I set this flag in migrate.php after a successful migration
    $migrationStatus = $mongodb->config->findOne(['_id' => 'migration_status']);
    $isMigrated      = $migrationStatus && !empty($migrationStatus['migrated']);

    if (!$isMigrated) {
        $errors[] = 'Please migrate SQL data to MongoDB before using this page.';
    }
} catch (Exception $e) {
    $errors[] = 'MongoDB connection failed: ' . $e->getMessage();
}

// user must be selected on homepage before using this page
if (!isset($_SESSION['user_id'])) {
    $errors[] = 'Please select a user on the homepage first.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && $isMigrated && $mongodb) {
    $actor_id   = trim($_POST['actor_id']   ?? '');
    $content_id = trim($_POST['content_id'] ?? '');

    // basic validation
    if (empty($actor_id))   $errors[] = 'Please select an actor.';
    if (empty($content_id)) $errors[] = 'Please select a title.';

    $actorDoc   = null;
    $contentDoc = null;

     // look up the actor document in MongoDB by _id
    if (!empty($actor_id)) {
        $actorDoc = $mongodb->actor->findOne(['_id' => (int)$actor_id]);
        if (!$actorDoc) $errors[] = 'Selected actor does not exist in MongoDB.';
    }

    // look up the content document in MongoDB by _id
    if (!empty($content_id)) {
        $contentDoc = $mongodb->content->findOne(['_id' => (int)$content_id]);
        if (!$contentDoc) $errors[] = 'Selected title does not exist in MongoDB.';
    }

    // check if this actor is already in the content's actors array
    // in MongoDB there's no composite primary key like in SQL so we
    // have to check manually by looping through the embedded array
    if ($actorDoc && $contentDoc) {
        $alreadyAssigned = false;
        foreach ($contentDoc['actors'] ?? [] as $a) {
            if ((int)$a['actor_id'] === (int)$actor_id) {
                $alreadyAssigned = true;
                break;
            }
        }
        if ($alreadyAssigned) {
            $errors[] = 'This actor is already assigned to that title.';
        }
    }

    if (empty($errors)) {
         // use $push to add the actor into the embedded actors array
        // inside the content document - this replaces what was an
        // INSERT into actor_content in the SQL version
        $result = $mongodb->content->updateOne(
            ['_id' => (int)$content_id],
            ['$push' => ['actors' => [
                'actor_id'      => (int)$actorDoc['_id'],
                'name'          => (string)$actorDoc['name'],
                'num_of_awards' => (int)$actorDoc['num_of_awards'],
            ]]]
        );

        if ($result->getModifiedCount() > 0) {
            $success = "Actor '{$actorDoc['name']}' successfully assigned to '{$contentDoc['title']}'.";
            $_POST = [];
        } else {
            $errors[] = 'Update failed — no document was modified.';
        }
    }
}

// Selected filter values — empty string means no filter applied
$selected_genre    = $_GET['genre']    ?? '';
$selected_actor_id = $_GET['actor_id'] ?? '';

if ($isMigrated && $mongodb) {
    // Dropdowns for use case form
    foreach ($mongodb->actor->find([], ['sort' => ['name' => 1]]) as $a) {
        $actors[] = [
            'actor_id'      => (int)$a['_id'],
            'name'          => (string)$a['name'],
            'num_of_awards' => (int)$a['num_of_awards'],
        ];
    }
    $actors_filter = $actors; // same list used for the report filter

    // load all content titles for the use case form dropdown
    // we only need _id, title and type so we use projection to avoid
    // fetching the whole document including embedded actors and reviews
    foreach ($mongodb->content->find([], ['sort' => ['title' => 1], 'projection' => ['_id' => 1, 'title' => 1, 'type' => 1]]) as $c) {
        $content[] = [
            'content_id' => (int)$c['_id'],
            'title'      => (string)$c['title'],
            'type'       => (string)($c['type'] ?? 'unknown'),
        ];
    }

    // Dynamic genre list : get all distinct genres from the content collection for the filter dropdown
    $genreResults = $mongodb->content->distinct('genre');
    sort($genreResults);
    $genres = $genreResults;

     // build the aggregation pipeline for the analytics report
    // I add $match stages only if the filters are actually active
    $matchStage = [];
    if (!empty($selected_genre))    $matchStage['genre']           = $selected_genre;
    if (!empty($selected_actor_id)) $matchStage['actors.actor_id'] = (int)$selected_actor_id;

    $pipeline = [];
    if (!empty($matchStage)) {
        $pipeline[] = ['$match' => $matchStage];
    }
    $pipeline[] = ['$unwind' => '$actors'];

    // re-apply the actor filter AFTER unwind
    // this is needed because before unwind I only filtered documents
    // that contain the actor, but after unwind I need to keep only
    // the specific actor row and drop the others
    if (!empty($selected_actor_id)) {
        $pipeline[] = ['$match' => ['actors.actor_id' => (int)$selected_actor_id]];
    }

    // select only the fields we want to display in the report table (limit to 5)
    $pipeline[] = ['$project' => [
        'content_title' => '$title',
        'genre'         => '$genre',
        'actor_name'    => '$actors.name',
    ]];
    if (empty($selected_genre) && empty($selected_actor_id)) {
        $pipeline[] = ['$limit' => 5];
    }

    $report = $mongodb->content->aggregate($pipeline)->toArray();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign Actor (MongoDB) - Watchfolio</title>
    <link rel="stylesheet" href="assets/css/pixel-theme.css">
    <style>
        :root {
            --primary: #ffaac7; --secondary: #e9c3d5; --accent: #f3c3cf;
            --danger: #ffc9e6; --bg: #fbd6ee; --card-bg: #ffffff; --text: #333333;
        }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: var(--bg); color: var(--text); line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .card { background: var(--card-bg); padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); margin-bottom: 20px; border: 1px solid #e2e8f0; }
        .card h2 { margin-top: 0; color: var(--primary); font-size: 1.5rem; display: flex; align-items: center; gap: 8px; }
        a { color: var(--primary); text-decoration: none; font-weight: 500; }
        a:hover { text-decoration: underline; }
        .btn, button { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--primary); color: #333; border-radius: 8px; border: none; margin-top: 12px; transition: all 0.2s ease; text-decoration: none; font-weight: 500; cursor: pointer; }
        .btn:hover, button:hover { background: var(--secondary); color: #333; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-decoration: none; }
        label { display: block; margin-top: 14px; font-weight: 700; }
        input, select { width: 100%; padding: 10px; margin-top: 6px; border-radius: 8px; border: 1px solid var(--secondary); box-sizing: border-box; font-size: 15px; }
        .success { background: #e8ffe8; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .error { background: #ffe8ee; color: #9f1239; padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .error ul { margin: 10px 0 0 0; padding-left: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 10px; text-align: left; }
        th { background: var(--primary); }
        .header { text-align: center; margin: 40px 0 30px; }
        .header h1 { font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; }
        .filter-form { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { margin-top: 0; font-weight: 700; font-size: 14px; }
        .filter-group select { width: auto; min-width: 160px; margin-top: 0; }
        .filter-form button { margin-top: 0; padding: 10px 20px; }
        .active-filters { font-size: 13px; color: #666; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="user-bar">
        <span class="brand"><span class="pixel-symbol pixel-user" aria-hidden="true"></span>Assign Actor (MongoDB)</span>
        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-info"><span class="pixel-symbol pixel-user small" aria-hidden="true"></span><?= htmlspecialchars($_SESSION['username']) ?></div>
        <?php endif; ?>
    </div>

    <div class="container">
        <div class="header">
            <h1>Assign Actor (MongoDB)</h1>
            <p>Link an actor to a movie or TV show in MongoDB.</p>
        </div>

        <!-- USE CASE FORM -->
        <div class="card">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!$isMigrated): ?>
                <a href="migrate.php" class="btn">
                    <span class="pixel-symbol pixel-rocket small" aria-hidden="true"></span>
                    Migrate SQL Data to MongoDB first
                </a>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <a href="index.php" class="btn">
                    <span class="pixel-symbol pixel-user small" aria-hidden="true"></span>
                    Select a user on the homepage first
                </a>
            <?php else: ?>
                <form method="POST">
                    <h2><span class="pixel-symbol pixel-movie" aria-hidden="true"></span>Select Title</h2>
                    <label>Title *</label>
                    <select name="content_id">
                        <option value="">-- Select a Title --</option>
                        <?php foreach ($content as $c): ?>
                            <option value="<?= (int)$c['content_id'] ?>"
                                <?= (isset($_POST['content_id']) && $_POST['content_id'] == $c['content_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['title']) ?> (<?= htmlspecialchars($c['type']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <h2 style="margin-top: 25px;"><span class="pixel-symbol pixel-user" aria-hidden="true"></span>Select Actor</h2>
                    <label>Actor *</label>
                    <select name="actor_id">
                        <option value="">-- Select an Actor --</option>
                        <?php foreach ($actors as $a): ?>
                            <option value="<?= (int)$a['actor_id'] ?>"
                                <?= (isset($_POST['actor_id']) && $_POST['actor_id'] == $a['actor_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['name']) ?> (<?= (int)$a['num_of_awards'] ?> awards)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">
                        <span class="pixel-symbol pixel-star small" aria-hidden="true"></span>
                        Assign Actor
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- ANALYTICS REPORT -->
        <div class="card">
            <h2><span class="pixel-symbol pixel-star" aria-hidden="true"></span>Analytics: Actor Assignments (MongoDB)</h2>
            <p>Shows up to 5 actor-content assignments. Filter by genre or actor to narrow results.</p>

            <?php if ($isMigrated && !empty($genres)): ?>
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="genre">Genre</label>
                        <select name="genre" id="genre">
                            <option value="">-- All Genres --</option>
                            <?php foreach ($genres as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>" <?= $g === $selected_genre ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="actor_id">Actor</label>
                        <select name="actor_id" id="actor_id">
                            <option value="">-- All Actors --</option>
                            <?php foreach ($actors_filter as $a): ?>
                                <option value="<?= (int)$a['actor_id'] ?>" <?= $a['actor_id'] == $selected_actor_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($a['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit">Apply</button>
                    <?php if (!empty($selected_genre) || !empty($selected_actor_id)): ?>
                        <a href="?" class="btn" style="margin-top:0; padding: 10px 20px; background: var(--secondary);">Clear</a>
                    <?php endif; ?>
                </form>

                <?php
                    $active = [];
                    if (!empty($selected_genre)) $active[] = 'Genre: <strong>' . htmlspecialchars($selected_genre) . '</strong>';
                    if (!empty($selected_actor_id)) {
                        foreach ($actors_filter as $a) {
                            if ($a['actor_id'] == $selected_actor_id) {
                                $active[] = 'Actor: <strong>' . htmlspecialchars($a['name']) . '</strong>';
                                break;
                            }
                        }
                    }
                    if (!empty($active)) echo '<p class="active-filters">Active filters: ' . implode(' &amp; ', $active) . '</p>';
                    else echo '<p class="active-filters">Showing all results.</p>';
                ?>

                <?php if (!empty($report)): ?>
                    <table>
                        <tr>
                            <th>Actor</th>
                            <th>Content Title</th>
                            <th>Genre</th>
                        </tr>
                        <?php foreach ($report as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['actor_name']) ?></td>
                                <td><?= htmlspecialchars($row['content_title']) ?></td>
                                <td><?= htmlspecialchars($row['genre']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No results found. Try assigning an actor first or adjusting the filters.</p>
                <?php endif; ?>

            <?php elseif (!$isMigrated): ?>
                <p>Migrate SQL data to MongoDB first to view this report.</p>
            <?php endif; ?>
        </div>

        <a href="index.php" class="btn">
            <span class="pixel-symbol pixel-movie small" aria-hidden="true"></span>
            Return to Homepage
        </a>
    </div>
    <script src="assets/js/sparkle-cursor.js"></script>
</body>
</html>