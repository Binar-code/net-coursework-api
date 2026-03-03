CREATE DATABASE IF NOT EXISTS rss_feed
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rss_feed;

CREATE TABLE IF NOT EXISTS articles (
                                        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                        title           VARCHAR(512)    NOT NULL,
    link            VARCHAR(2048)   NOT NULL,
    description     TEXT            NOT NULL,
    published_at    DATETIME        NOT NULL COMMENT 'Publication date from RSS',
    fetched_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When we fetched it',
    description_len INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Length of description in chars',

    UNIQUE KEY uq_link (link(768)),
    INDEX idx_published  (published_at),
    INDEX idx_fetched    (fetched_at),
    INDEX idx_desc_len   (description_len),
    FULLTEXT idx_ft_search (title, description)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
