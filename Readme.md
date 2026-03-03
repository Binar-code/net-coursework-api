# API Ленты RSS Habr

PHP + MySQL REST API сервер для загрузки, хранения и фильтрации статей из RSS ленты Habr.
Упакован в Docker для развёртывания одной командой.

## Быстрый старт

```bash
# Клонируйте или скопируйте проект, затем:
docker-compose up -d --build

# Подождите ~10 секунд инициализации MySQL, затем загрузите статьи:
curl -X POST http://localhost:8080/api/fetch
```

API теперь работает по адресу **http://localhost:8080**.

> **Примечание:** Если вы обновили проект и видите ошибку "table not found", выполните `docker-compose down -v && docker-compose up -d --build` для пересоздания контейнеров.

---

## Конечные точки API

### `GET /`

Возвращает информацию об API и список всех доступных конечных точек с описанием параметров.

---

### `POST /api/fetch`

Загружает RSS ленту Habr и добавляет новые статьи в базу данных.
Вызывайте периодически (например, через cron) для обновления данных.

**Ответ:**
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

Получить список статей с фильтрацией, сортировкой и постраничной выдачей.

#### Постраничная выдача

| Параметр   | Тип | По умолчанию | Описание              |
|------------|-----|--------------|----------------------|
| `page`     | int | 1            | Номер страницы       |
| `per_page` | int | 20           | Статей на странице (максимум 100) |

#### Сортировка

| Параметр     | Тип    | По умолчанию   | Значения                                              |
|--------------|--------|----------------|-------------------------------------------------------|
| `sort_by`    | string | `published_at` | `published_at`, `fetched_at`, `description_len`, `title` |
| `sort_order` | string | `desc`         | `asc`, `desc`                                         |

#### Фильтры

| Параметр       | Тип    | Описание                           |
|----------------|--------|-----------------------------------|
| `date_from`    | string | Опубликовано после (ISO дата)     |
| `date_to`      | string | Опубликовано до (ISO дата)        |
| `desc_len_min` | int    | Минимальная длина описания (символы) |
| `desc_len_max` | int    | Максимальная длина описания (символы) |
| `keywords`     | string | Слова через запятую, **максимум 3** |
| `keyword_logic`| string | `AND` / `OR` / `NOT` (по умолчанию `AND`)|

#### Структура статьи в ответе

Каждая статья содержит следующие поля:

| Поле              | Тип              | Описание                                  |
|-------------------|------------------|-------------------------------------------|
| `id`              | int              | Уникальный ID статьи                      |
| `title`           | string           | Название статьи                           |
| `link`            | string           | Ссылка на статью                          |
| `description`     | string           | Текст описания статьи                     |
| `published_at`    | string (datetime)| Дата публикации в формате ISO             |
| `fetched_at`      | string (datetime)| Дата загрузки в БД в формате ISO         |
| `description_len` | int              | Длина описания в символах                 |
| `tags`            | array            | Массив тегов/категорий статьи             |
| `tags[].id`       | int              | ID тега                                   |
| `tags[].name`     | string           | Название тега                            |

**Объяснение логики ключевых слов:**
- `AND` — статья должна содержать **все** указанные ключевые слова (в названии или описании)
- `OR`  — статья должна содержать **хотя бы одно** ключевое слово
- `NOT` — статья не должна содержать **никакие** ключевые слова

#### Примеры

```bash
# Последние 10 статей
curl "http://localhost:8080/api/articles?per_page=10"

# Статьи из марта 2026, отсортированные по длине описания
curl "http://localhost:8080/api/articles?date_from=2026-03-01&date_to=2026-03-31&sort_by=description_len&sort_order=desc"

# Только короткие описания (менее 200 символов)
curl "http://localhost:8080/api/articles?desc_len_max=200"

# Статьи, содержащие "Python" И "AI"
curl "http://localhost:8080/api/articles?keywords=Python,AI&keyword_logic=AND"

# Статьи, содержащие "Go" ИЛИ "Rust"
curl "http://localhost:8080/api/articles?keywords=Go,Rust&keyword_logic=OR"

# Исключить статьи о JavaScript
curl "http://localhost:8080/api/articles?keywords=JavaScript&keyword_logic=NOT"
```

**Ответ:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Название статьи",
      "link": "https://habr.com/...",
      "description": "Текст описания статьи...",
      "published_at": "2026-03-03 12:00:00",
      "fetched_at": "2026-03-03 14:30:00",
      "description_len": 342,
      "tags": [
        {"id": 5, "name": "Python"},
        {"id": 12, "name": "Веб-разработка"}
      ]
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

Получить одну статью по её ID. Возвращает статью со всеми её тегами.

```bash
curl http://localhost:8080/api/articles/42
```

**Пример ответа:**
```json
{
  "id": 42,
  "title": "Интересная статья",
  "link": "https://habr.com/...",
  "description": "Описание статьи...",
  "published_at": "2026-03-03 12:00:00",
  "fetched_at": "2026-03-03 14:30:00",
  "description_len": 500,
  "tags": [
    {"id": 1, "name": "Python"},
    {"id": 2, "name": "Алгоритмы"},
    {"id": 3, "name": "Оптимизация"}
  ]
}
```

---

### `GET /api/stats`

Статистика базы данных: общее количество статей, диапазон дат, средняя длина описания.

```bash
curl http://localhost:8080/api/stats
```

---

## Схема базы данных

```sql
CREATE TABLE articles (
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
);

CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    INDEX idx_name (name)
);

CREATE TABLE article_tags (
    article_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);
```

---

## Конфигурация

Переменные окружения (установлены в `docker-compose.yml`):

| Переменная | По умолчанию                        | Описание        |
|-----------|--------------------------------------|--------------------|
| `DB_HOST` | `db`                                 | Хост MySQL         |
| `DB_PORT` | `3306`                               | Порт MySQL         |
| `DB_NAME` | `rss_feed`                           | Имя базы данных    |
| `DB_USER` | `rss_user`                           | Пользователь БД    |
| `DB_PASS` | `rss_secret`                         | Пароль БД          |
| `RSS_URL` | `https://habr.com/ru/rss/articles/`  | URL RSS ленты      |

---

## Периодическая загрузка (Cron)

Для автоматической загрузки новых статей каждые 15 минут добавьте в crontab хоста:

```bash
*/15 * * * * curl -s -X POST http://localhost:8080/api/fetch > /dev/null 2>&1
```

---


## Остановка

```bash
docker-compose down          # остановить контейнеры, сохранить данные
docker-compose down -v       # остановить и удалить данные MySQL
```
