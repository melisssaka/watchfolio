// Student2_Indexes.js 
// MongoDB indexes for Add Movie use case
// by Melis Saka (Student 2)

// Compound index on content collection to speed up the Drama directors analytics report.
// The $match stage always filters by type and genre, so indexing both fields
// Avoids a full collection scan (COLLSCAN -> IXSCAN)
// Before: totalDocsExamined = 42 (COLLSCAN)
// After:  totalDocsExamined = 3  (IXSCAN)
db.content.createIndex(
  { type: 1, genre: 1 },
  { name: "idx_content_type_genre" }
)