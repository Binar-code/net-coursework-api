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

### `GET|POST /api/fetch`

Загружает RSS ленту Habr и добавляет новые статьи в базу данных.
Принимает как GET (удобно из браузера), так и POST (для cron и скриптов).

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

Параметр `filter` позволяет комбинировать до **3-х условий** фильтрации с логическими связками `AND`, `OR`, `NOT`.

**Формат:** `filter=тип=значение_ОПЕРАТОР_тип=значение`

**Типы фильтров:**

| Тип        | Описание                | Формат значения                              |
|------------|------------------------|----------------------------------------------|
| `date`     | По дате добавления     | `YYYY-MM-DD` или диапазон `YYYY-MM-DD..YYYY-MM-DD` |
| `desc_len` | По размеру описания    | число или диапазон `мин..макс`               |
| `keywords` | По ключевому слову     | строка (поиск в названии и описании)         |

**Логические связки (между типами фильтров):**

| Оператор | Описание                                        |
|----------|------------------------------------------------|
| `_AND_`  | Оба условия должны выполняться                  |
| `_OR_`   | Достаточно выполнения хотя бы одного условия    |
| `_NOT_`  | Следующее условие исключается (AND NOT)          |

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

#### Примеры

```bash
# Последние 10 статей
curl "http://localhost:8080/api/articles?per_page=10"

# Статьи за март 2026 с ключевым словом Python
curl "http://localhost:8080/api/articles?filter=date=2026-03-01..2026-03-31_AND_keywords=Python"

# Статьи с Python ИЛИ Rust
curl "http://localhost:8080/api/articles?filter=keywords=Python_OR_keywords=Rust"

# Короткие описания (до 200 символов), исключая JavaScript
curl "http://localhost:8080/api/articles?filter=desc_len=0..200_NOT_keywords=JavaScript"

# Статьи добавленные после 2026-03-01, отсортированные по длине описания
curl "http://localhost:8080/api/articles?filter=date=2026-03-01&sort_by=description_len&sort_order=desc"

# Комбинация трёх фильтров: дата + размер описания + ключевое слово
curl "http://localhost:8080/api/articles?filter=date=2026-01-01..2026-12-31_AND_desc_len=100..500_AND_keywords=Python"
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
