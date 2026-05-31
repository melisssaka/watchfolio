CREATE TABLE app_user (
    user_id INT PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    name VARCHAR(100),
    email VARCHAR(100),
    birth_year INT,
    gender VARCHAR(100),
    password VARCHAR(255)
);

CREATE TABLE director (
    director_id INT PRIMARY KEY,
    name VARCHAR(100),
    nationality VARCHAR(100)
);

CREATE TABLE actor (
    actor_id INT PRIMARY KEY,
    name VARCHAR(100),
    num_of_awards INT
);

CREATE TABLE content (
    content_id INT PRIMARY KEY,
    title VARCHAR(100),
    genre VARCHAR(100),
    release_year INT,
    created_by_user INT,

    FOREIGN KEY (created_by_user)
        REFERENCES app_user(user_id)
);

CREATE TABLE movie (
    content_id INT PRIMARY KEY,
    duration INT,
    box_office BIGINT,
    director_id INT,

    FOREIGN KEY (content_id)
        REFERENCES content(content_id),

    FOREIGN KEY (director_id)
        REFERENCES director(director_id)
);

CREATE TABLE tv_show (
    content_id INT PRIMARY KEY,
    num_episodes INT,
    num_seasons INT,

    FOREIGN KEY (content_id)
        REFERENCES content(content_id)
);

CREATE TABLE tv_shows_directors (
    content_id INT,
    director_id INT,

    PRIMARY KEY (content_id, director_id),

    FOREIGN KEY (content_id)
        REFERENCES tv_show(content_id),

    FOREIGN KEY (director_id)
        REFERENCES director(director_id)
);

CREATE TABLE review (
    review_number INT,
    user_id INT,
    content_id INT,
    rating INT,
    review_text VARCHAR(10000),
    parent_review_number INT NULL,
    parent_user_id INT NULL,
    parent_content_id INT NULL,

    PRIMARY KEY (review_number, user_id, content_id),

    FOREIGN KEY (user_id)
        REFERENCES app_user(user_id),

    FOREIGN KEY (content_id)
        REFERENCES content(content_id),

    FOREIGN KEY (
        parent_review_number,
        parent_user_id,
        parent_content_id
    )
        REFERENCES review (
            review_number,
            user_id,
            content_id
        )
);

CREATE TABLE actor_content (
    actor_id INT,
    content_id INT,

    PRIMARY KEY (actor_id, content_id),

    FOREIGN KEY (actor_id)
        REFERENCES actor(actor_id),

    FOREIGN KEY (content_id)
        REFERENCES content(content_id)
);
