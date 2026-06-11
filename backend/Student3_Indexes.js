// This index helps speed up the analytics report in assign_actor_mongo.php
// The report filters content by genre and actor, so we index both fields together
// Without this index, MongoDB scans all 40 documents every time (COLLSCAN)
// With this index, it only looks at the matching ones (IXSCAN) - 9 docs instead of 40
// "actors.actor_id" is inside an embedded array so MongoDB makes it a multikey index automatically
db.content.createIndex(
    { genre: 1, "actors.actor_id": 1 },
    { name: "idx_genre_actor" }
  );
  
  print("Index idx_genre_actor created on content collection.");
  print("Current indexes on content collection:");
  printjson(db.content.getIndexes());