<!DOCTYPE html>
<html>
<head>
    <title>Watchfolio</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .card { background: #f5f5f5; padding: 20px; margin: 10px 0; border-radius: 8px; }
        a { color: #333; text-decoration: none; font-weight: bold; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 12px 24px; background: #333; color: white; border-radius: 5px; margin-top: 8px; }
        .btn:hover { background: #555; color: white; }
        .btn-red { background: #c0392b; }
        .btn-red:hover { background: #e74c3c; }
    </style>
</head>
<body>
    <h1>🎬 Watchfolio</h1>
    <p>Welcome to Watchfolio — your movie and TV show database.</p>

    <div class="card">
        <h2>⚙️ Data Setup</h2>
        <p>Reset and fill the database with randomized data.</p>
        <a href="generate_database.php" class="btn btn-red">🔄 Generate Data</a>
    </div>

    <div class="card">
        <h2>📝 Use Cases</h2>
        <a href="add_movie.php" class="btn">🎬 Add New Movie</a>
    </div>

</body>
</html>