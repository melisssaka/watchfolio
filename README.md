# Watchfolio – Milestone 2

A movie and TV show tracker web app. Runs three Docker containers: the web app (PHP 8.2 + Apache), MariaDB (SQL), and MongoDB (NoSQL).

## Requirements
- Docker
- unzip

## How to run
1. Unzip the submission
2. Navigate into the folder: `cd watchfolio`
3. Build and start the containers: `docker compose up --build`
4. Open http://localhost:8080 in your browser
5. Click **Generate Data** to create the database with randomised data
6. Select a user from the dropdown to have a logged-in session
7. Use the SQL use cases
8. Click **Migrate to MongoDB** to switch to MongoDB mode
9. Use the MongoDB use cases

## MongoDB indexes
The index scripts for each use case are in the project root:
`Student1_Indexes.js`, `Student2_Indexes.js`, `Student3_Indexes.js`.

## Image sources
The background images in `backend/images/` are not original. Sources:

1.jpeg : https://de.pinterest.com/pin/210402613837031303/
10.jpeg : https://share.google/sABTwoKmcD256xZiw
11.jpeg : https://mx.pinterest.com/pin/140806233907393/
12.jpeg : https://kr.pinterest.com/pin/467107792630416381/
13.jpeg: https://x.com/vidhvatm/status/1991140132510990517
14.jpeg: https://www.pinterest.com/pin/355854808089212149/
15.jpeg : https://in.pinterest.com/pin/291748882131635419/
2.jpeg: https://www.threads.com/@oddatide/post/DWl8IZXDRt0/media
3.jpeg: https://ceskepodcasty.cz/blog/10-podcastu-pro-pary-vse-o-vztazich-a-lasce?utm_source=spolecnost&utm_medium=banner&utm_campaign=laska
4.jpeg : https://cz.pinterest.com/pin/211174979068417/
5.jpeg: https://share.google/TE6tDciH1ETwS7092
6.jpeg : https://share.google/NC2wPumQIZ8AHxLPb
7.jpeg: https://www.pinterest.com/pin/338755203248559036/
8.jpeg: https://share.google/iWaYI0js1EZFZeHBO
9.jpeg: https://share.google/M7nRJ7l0OiIZBqwCC
