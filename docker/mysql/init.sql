CREATE DATABASE IF NOT EXISTS rss_feed
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rss_feed;

CREATE TABLE IF NOT EXISTS articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(512) NOT NULL,
    link VARCHAR(2048) NOT NULL,
    description TEXT NOT NULL,
    published_at DATETIME NOT NULL,
    fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description_len INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_link (link(768)),
    INDEX idx_published (published_at),
    INDEX idx_fetched (fetched_at),
    INDEX idx_desc_len (description_len),
    FULLTEXT idx_ft_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS article_tags (
    article_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
