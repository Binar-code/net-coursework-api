# Habr RSS Feed API

PHP + MySQL REST API server for fetching, storing and filtering articles from Habr RSS feed.
Packaged with Docker for one-command deployment.

## Quick Start

```bash
# Clone / copy the project, then:
docker-compose up -d --build

# Wait ~10 seconds for MySQL to initialize, then fetch articles:
curl -X POST http://localhost:8080/api/fetch
```

The API is now running at **http://localhost:8080**.

---

## API Endpoints

### `GET /`

Returns API info and list of all available endpoints with parameter descriptions.

---

### `POST /api/fetch`

Fetches the Habr RSS feed and inserts new articles into the database.
Call this periodically (e.g. via cron) to keep data fresh.

**Response:**
```json
{
  "success": true,
  "inserted": 12,
  "skipped": 8,
  "total": 20
}
```

---

### `GET /api/articles`

List articles with filtering, sorting, and pagination.

#### Pagination

| Param      | Type | Default | Description              |
|------------|------|---------|--------------------------|
| `page`     | int  | 1       | Page number              |
| `per_page` | int  | 20      | Items per page (max 100) |

#### Sorting

| Param        | Type   | Default        | Values                                              |
|--------------|--------|----------------|------------------------------------------------------|
| `sort_by`    | string | `published_at` | `published_at`, `fetched_at`, `description_len`, `title` |
| `sort_order` | string | `desc`         | `asc`, `desc`                                        |

#### Filters

| Param          | Type   | Description                         |
|----------------|--------|-------------------------------------|
| `date_from`    | string | Published after (ISO date)          |
| `date_to`      | string | Published before (ISO date)         |
| `desc_len_min` | int    | Min description length (chars)      |
| `desc_len_max` | int    | Max description length (chars)      |
| `keywords`     | string | Comma-separated, **max 3** keywords |
| `keyword_logic`| string | `AND` / `OR` / `NOT` (default `AND`)|

**Keyword logic explained:**
- `AND` — article must contain **all** specified keywords (in title or description)
- `OR`  — article must contain **at least one** keyword
- `NOT` — article must contain **none** of the keywords

#### Examples

```bash
# Latest 10 articles
curl "http://localhost:8080/api/articles?per_page=10"

# Articles from March 2026, sorted by description length
curl "http://localhost:8080/api/articles?date_from=2026-03-01&date_to=2026-03-31&sort_by=description_len&sort_order=desc"

# Short descriptions only (under 200 chars)
curl "http://localhost:8080/api/articles?desc_len_max=200"

# Articles containing "Python" AND "AI"
curl "http://localhost:8080/api/articles?keywords=Python,AI&keyword_logic=AND"

# Articles containing "Go" OR "Rust"
curl "http://localhost:8080/api/articles?keywords=Go,Rust&keyword_logic=OR"

# Exclude articles about JavaScript
curl "http://localhost:8080/api/articles?keywords=JavaScript&keyword_logic=NOT"
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Article title",
      "link": "https://habr.com/...",
      "description": "Article description text...",
      "published_at": "2026-03-03 12:00:00",
      "fetched_at": "2026-03-03 14:30:00",
      "description_len": 342
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 156,
    "total_pages": 8
  },
  "sort": {
    "sort_by": "published_at",
    "sort_order": "DESC"
  }
}
```

---

### `GET /api/articles/{id}`

Get a single article by its ID.

```bash
curl http://localhost:8080/api/articles/42
```

---

### `GET /api/stats`

Database statistics: total articles, date range, average description length.

```bash
curl http://localhost:8080/api/stats
```

---

## Database Schema

```sql
CREATE TABLE articles (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(512)    NOT NULL,
    link            VARCHAR(2048)   NOT NULL,
    description     TEXT            NOT NULL,
    published_at    DATETIME        NOT NULL,
    fetched_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description_len INT UNSIGNED    NOT NULL DEFAULT 0,

    UNIQUE KEY  uq_link       (link(768)),
    INDEX       idx_published (published_at),
    INDEX       idx_fetched   (fetched_at),
    INDEX       idx_desc_len  (description_len),
    FULLTEXT    idx_ft_search (title, description)
);
```

---

## Configuration

Environment variables (set in `docker-compose.yml`):

| Variable  | Default                              | Description        |
|-----------|--------------------------------------|--------------------|
| `DB_HOST` | `db`                                 | MySQL host         |
| `DB_PORT` | `3306`                               | MySQL port         |
| `DB_NAME` | `rss_feed`                           | Database name      |
| `DB_USER` | `rss_user`                           | Database user      |
| `DB_PASS` | `rss_secret`                         | Database password  |
| `RSS_URL` | `https://habr.com/ru/rss/articles/`  | RSS feed URL       |

---

## Periodic Fetching (Cron)

To auto-fetch new articles every 15 minutes, add to crontab on the host:

```bash
*/15 * * * * curl -s -X POST http://localhost:8080/api/fetch > /dev/null 2>&1
```

---

## Stopping

```bash
docker-compose down          # stop containers, keep data
docker-compose down -v       # stop and delete MySQL data
```
