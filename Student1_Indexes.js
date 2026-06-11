// Student1_Indexes.js
// MongoDB index for Add Review use case
// by Lara Mijanovic 11847329

// Index on the embedded reviews.user_id field to speed up the user preference
// analytics report in add_review_mongo.php.
// The report first filters content documents by reviews.user_id, then unwinds
// the embedded reviews array and counts movie vs. TV show reviews.
// Before: totalDocsExamined = 40, totalKeysExamined = 0 (COLLSCAN)
// After:  MongoDB uses idx_reviews_user_id (IXSCAN)
db.content.createIndex(
  { "reviews.user_id": 1 },
  { name: "idx_reviews_user_id" }
);

print("Index idx_reviews_user_id created on content collection.");
print("Current indexes on content collection:");
printjson(db.content.getIndexes());
