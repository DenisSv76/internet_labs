# 🚀 Symfony Contact API — API обратной связи с модерацией через huggingface AI

Проект на базе **Symfony 7.4+** и **FrankenPHP**, реализующий backend для формы обратной связи с модерацией контента через **Huggingface API**, асинхронной отправкой email-уведомлений через **Symfony Messenger** и защитой от злоупотреблений с помощью **Rate Limiter**.

---

## 📋 Содержание

- [Возможности](#-возможности)
- [Технологический стек](#-технологический-стек)
- [Архитектура проекта](#-архитектура-проекта)
- [Структура проекта](#-структура-проекта)
- [Требования](#-требования)
- [Быстрый старт (разработка)](#-быстрый-старт-разработка)
- [Развёртывание в production](#-развёртывание-в-production)
- [API-документация](#-api-документация)
- [Переменные окружения](#-переменные-окружения)
- [Логирование](#-логирование)
- [Лицензия](#-лицензия)

---

## ✨ Возможности

- **📨 API формы обратной связи** — endpoint `POST /api/contact` для приёма сообщений от пользователей (имя, телефон, email, комментарий).
- **🛡️ Модерация через Huggingface AI** — каждое сообщение проверяется через Huggingface API на наличие нежелательного контента (насилие, оскорбления, спам и т.д.).
- **📧 Асинхронные уведомления** — после сохранения сообщения асинхронно отправляются два email: владельцу сайта и копия отправителю. Используется Symfony Messenger с транспортом на базе Doctrine (MySQL).
- **⏱️ Rate Limiting** — защита API от злоупотреблений: не более 1 запроса в минуту с одного IP.
- **📝 Валидация данных** — строгая валидация всех полей сообщения через DTO и Symfony Validator.
- **📊 Логирование запросов** — все входящие запросы и исходящие ответы логируются в отдельный файл `requests.log` через кастомный event listener.
- **📖 API-документация** — автоматически генерируемая документация Swagger/OpenAPI через NelmioApiDocBundle (доступна по `/api/doc`).
- **🐳 Полная Docker-инфраструктура** — разработка и production через Docker Compose, FrankenPHP + Caddy в качестве веб-сервера, MySQL 8, Mailpit для тестирования почты.

---

## 🧰 Технологический стек

| Компонент | Технология |
|---|---|
| **Язык** | PHP 8.4 |
| **Фреймворк** | Symfony 7.4 |
| **Веб-сервер** | FrankenPHP (Caddy) |
| **База данных** | MySQL 8 |
| **ORM** | Doctrine 3 |
| **Модерация** | Huggingface API |
| **Очереди** | Symfony Messenger (Doctrine transport) |
| **Отправка почты** | Symfony Mailer (SMTP Яндекс) |
| **Rate Limiting** | Symfony Rate Limiter (Token Bucket) |
| **Логирование** | Monolog |
| **API-документация** | NelmioApiDocBundle (Swagger/OpenAPI) |
| **Контейнеризация** | Docker + Docker Compose |

---

## 🏗️ Архитектура проекта

### Схема обработки запроса

```
POST /api/contact
        │
        ▼
┌──────────────────┐
│  ContactController│  ← принимает JSON-запрос
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ RateLimiterService│  ← проверка лимита по IP (не более 10/мин)
└────────┬─────────┘
         │ OK
         ▼
┌──────────────────┐
│  MessageService   │
│  ├─ Валидация DTO │  ← проверка полей (not blank, email, regex)
│  ├─ Модерация     │  ← запрос к Huggingface AI
│  ├─ Сохранение    │  ← запись в таблицу message (Doctrine)
│  └─ Отправка в    │  ← dispatch async message
│     очередь       │
└────────┬─────────┘
         │ (асинхронно)
         ▼
┌──────────────────┐
│NotificationHandler│  ← обрабатывает сообщение из очереди
│  ├─ Email владельцу│
│  └─ Копия отправителю│
└──────────────────┘
```

### ER-диаграмма таблицы `message`

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Первичный ключ |
| `name` | VARCHAR(40) | Имя отправителя |
| `email` | VARCHAR(100) | Email отправителя |
| `phone` | VARCHAR(20) | Телефон |
| `ip` | VARCHAR(45) | IP-адрес отправителя |
| `user_agent` | VARCHAR(500) | User-Agent браузера |
| `comment` | VARCHAR(255) | Текст сообщения |
| `notification_sent` | TINYINT(1) | Флаг отправки уведомления |
| `created_at` | DATETIME | Дата создания |
| `updated_at` | DATETIME | Дата обновления |

---

## 📁 Структура проекта

```
symf_internet_lab/
├── .env                        # Переменные окружения (не для production-секретов)
├── .env.dev                    # Dev-специфичные переменные (APP_SECRET)
├── compose.yaml                # Базовый Docker Compose
├── compose.override.yaml       # Dev-оверрайд (монтирование томов, Mailpit, Xdebug)
├── compose.prod.yaml           # Production-оверрайд
├── Dockerfile                  # Многоэтапная сборка FrankenPHP (base/dev/prod)
├── frankenphp/
│   ├── Caddyfile               # Конфигурация Caddy веб-сервера
│   ├── worker.Caddyfile        # Конфигурация FrankenPHP worker-режима (production)
│   ├── docker-entrypoint.sh    # Точка входа контейнера
│   └── conf.d/
│       ├── 10-app.ini          # Общие PHP-настройки
│       ├── 20-app.dev.ini      # Dev PHP-настройки (Xdebug)
│       └── 20-app.prod.ini     # Production PHP-настройки (opcache)
├── config/
│   ├── services.yaml           # DI-контейнер: привязка аргументов сервисов
│   ├── routes.yaml             # Роутинг контроллеров
│   ├── bundles.php             # Регистрация бандлов
│   └── packages/
│       ├── doctrine.yaml       # Конфигурация Doctrine ORM
│       ├── doctrine_migrations.yaml
│       ├── framework.yaml      # Symfony Framework
│       ├── messenger.yaml      # Symfony Messenger (async транспорт)
│       ├── monolog.yaml        # Логирование (каналы, хендлеры)
│       ├── nelmio_api_doc.yaml # Swagger-документация
│       ├── rate_limiter.yaml   # Rate Limiter (token_bucket: 10/мин)
│       ├── security.yaml       # Базовая конфигурация безопасности
│       ├── validator.yaml      # Symfony Validator
│       └── mailer.yaml         # Mailer (SMTP)
├── migrations/
│   └── Version20260728144630.php  # Создание таблицы message
├── public/
│   └── index.php               # Фронт-контроллер
├── src/
│   ├── Kernel.php              # Ядро приложения
│   ├── Controller/
│   │   └── ContactController.php   # Контроллер формы обратной связи
│   ├── DTO/
│   │   ├── MessageDto.php          # DTO с валидацией полей сообщения
│   │   └──  ModerateMessageDto.php  # DTO результата модерации OpenAI
│   ├── Entity/
│   │   └── Message.php             # Сущность Doctrine (таблица message)
│   ├── EventListener/
│   │   └── RequestLoggerListener.php  # Логирование входящих/исходящих запросов
│   ├── Message/
│   │   └── Notification.php        # Класс асинхронного сообщения Messenger
│   ├── MessageHandler/
│   │   └── NotificationHandler.php # Обработчик: отправка email-уведомлений
│   ├── Repository/
│   │   └── MessageRepository.php   # Репозиторий Doctrine
│   └── Service/
│       ├── MessageService.php               # Бизнес-логика: валидация, модерация, сохранение
│       ├── HuggingFaceModerationService.php # Интеграция с HuggingFace API
│       └── RateLimiterService.php           # Сервис rate limiting
└── docs/                        # Документация (от поставщика шаблона)
```

---

## 📌 Требования

- **Docker** и **Docker Compose** (рекомендуется Docker Desktop или Docker Engine 24+)
- **Git**
- Свободные порты:
  - `8080` (HTTP)
  - `8443` (HTTPS/HTTP3)
  - `9306` (MySQL, только если нужен внешний доступ)
- **API-ключ HuggingFace AI** (для работы модерации сообщений)

---

## 🚀 Быстрый старт (разработка)

### 1. Клонирование репозитория

```bash
git clone <url-репозитория> symf_internet_lab
cd symf_internet_lab
```

### 2. Настройка переменных окружения

Скопируйте и отредактируйте `.env` файл:

```bash
cp .env .env.local
```

Укажите в `.env.local` актуальные значения:

```ini
# OpenAI API ключ (обязательно!)
HUGGINGFACE_API_KEY="sk-ваш-реальный-ключ"

# Email владельца для получения уведомлений
MY_EMAIL=ваша@почта(замените на свою почту!)

# Настройки почтового сервера (SMTP Яндекс)
MAILER_DSN=smtp://ваша@почта:пароль@smtp.ваша@почта:465?encryption=ssl(замените на подключение к своей почте)

# Секретный ключ приложения
APP_SECRET=ваш_сгенерированный_секрет

# Настройки MySQL (опционально, есть значения по умолчанию)
MYSQL_ROOT_PASSWORD=root
MYSQL_USER=user
MYSQL_PASSWORD=user
MYSQL_DATABASE=landing
```

> ⚠️ **Важно**: файл `.env.local` не коммитится в Git (он в `.gitignore`). Используйте его для хранения чувствительных данных.

### 3. Запуск Docker-контейнеров

```bash
# Сборка и запуск в режиме разработки
docker compose up -d --build
```

Процесс запуска:
1. Сборка образа `frankenphp_dev` (PHP 8.4 + Xdebug + Composer)
2. Запуск контейнера MySQL 8
3. Запуск контейнера Mailpit (SMTP-перехватчик для тестирования писем)
4. Запуск контейнера FrankenPHP с Caddy

### 4. Применение миграций базы данных

```bash
# Применить миграции Doctrine
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Очистка кэша (при необходимости)

```bash
docker compose exec php bin/console cache:clear
```

### 6. Проверка работоспособности

```bash
# Проверка API — отправка тестового сообщения
curl -X POST http://localhost/api/contact \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Иван",
    "phone": "+79991234567",
    "email": "ivan@example.com",
    "comment": "Тестовое сообщение"
  }'
```

Ожидаемый ответ (успех):
```json
{"message_id": 1}
```

Также доступна [Postman-коллекция](postman/collection.json) с готовыми примерами всех сценариев (успех, ошибки валидации, модерация, rate limit).

### 7. Полезные команды для разработки

```bash
# Просмотр логов приложения
docker compose logs -f php

# Зайти в контейнер PHP
docker compose exec php bash

# Проверка почты в Mailpit (интерфейс доступен на порту 8025)
# Откройте в браузере: http://localhost:8025

# Просмотр логов воркера очередей
docker compose logs -f worker

# Просмотр статуса очередей
docker compose exec php bin/console messenger:stats
```

---

## 🏭 Развёртывание в production

### 1. Подготовка

Создайте файл `.env.local` на сервере с production-настройками:

```ini
APP_ENV=prod
APP_SECRET=<сгенерированный-секрет>
OPENAI_API_KEY="sk-ваш-продакшн-ключ"
MY_EMAIL=ваша@почта(замените на свою почту!)
MAILER_DSN=smtp://ваша@почта:пароль@smtp.ваша@почта:465?encryption=ssl(замените на подключение к своей почте)
```

### 2. Запуск

```bash
# Сборка production-образа и запуск
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

### 3. Применение миграций

```bash
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

### 4. Воркер очередей

Сервис `worker` уже определён в [`compose.yaml`](compose.yaml) и запускается автоматически вместе с остальными контейнерами:

```bash
docker compose up -d   # worker стартует автоматически
```

Проверить, что воркер работает:

```bash
docker compose logs worker
```

Никаких дополнительных действий для обработки очереди не требуется ни в dev, ни в production.

### Отличия production-окружения

| Параметр | Dev | Production |
|---|---|---|
| **PHP-образ** | `frankenphp_dev` | `frankenphp_prod` |
| **OPcache** | Выключен | Включен (opcache.validate_timestamps=0) |
| **FrankenPHP режим** | Обычный + `--watch` | Worker-режим |
| **Xdebug** | Доступен | Отсутствует |
| **Логи** | `var/log/dev.log` | `stderr` (JSON-формат) |
| **Mailpit** | Да (порт 8025) | Нет (реальный SMTP) |
| **Монтирование кода** | Да (bind mount) | Нет (скопирован в образ) |
| **Composer** | dev-зависимости | Только production |

---

## 📖 API-документация

После запуска проекта Swagger-документация доступна по адресу:

```
http://localhost:8080/api/doc
```

### Единственный endpoint

#### `POST /api/contact`

Отправка сообщения через форму обратной связи.

**Тело запроса** (JSON):

```json
{
  "name": "Иван",
  "phone": "+79991234567",
  "email": "ivan@example.com",
  "comment": "Текст сообщения..."
}
```

| Поле | Тип | Обязательное | Ограничения |
|---|---|---|---|
| `name` | string | ✅ | 2-40 символов |
| `phone` | string | ✅ | До 20 символов, формат: `+`, цифры, пробелы, `-`, `()`, от 7 до 20 знаков |
| `email` | string | ✅ | Валидный email, до 100 символов |
| `comment` | string | ✅ | 1-255 символов |

**Успешный ответ** (200 OK):
```json
{
  "message_id": 42
}
```

**Ошибка валидации** (400 Bad Request):
```json
{
  "error": "There are errors ..."
}
```

**Сообщение не прошло модерацию** (422 Bad Request):
```json
{
  "error": "Ваше сообщение неприемлемо по следующей причине: Обнаружены нарушения в категориях: hate, harassment"
}
```

**Превышен лимит запросов** (429 Too Many Requests):
```json
{
  "error": "Too many requests"
}
```

---

## 🔧 Переменные окружения

### Основные

| Переменная | Описание                              | По умолчанию |
|---|---------------------------------------|---|
| `APP_ENV` | Окружение (`dev` / `prod` / `test`)   | `dev` |
| `APP_SECRET` | Секретный ключ приложения             | — (обязательно) |
| `HUGGINGFACE_API_KEY` | API-ключ HUGGINGFACE AI для модерации | — (обязательно) |
| `MY_EMAIL` | Email для получения уведомлений       | — (обязательно) |

### База данных

| Переменная | Описание | По умолчанию |
|---|---|---|
| `MYSQL_ROOT_PASSWORD` | Пароль root MySQL | `root` |
| `MYSQL_USER` | Пользователь MySQL | `user` |
| `MYSQL_PASSWORD` | Пароль пользователя MySQL | `user` |
| `MYSQL_DATABASE` | Название БД | `landing` |
| `MYSQL_VERSION` | Версия MySQL | `8` |
| `MYSQL_CHARSET` | Кодировка БД | `utf8mb4` |
| `DATABASE_URL` | Полный DSN Doctrine | формируется из переменных выше |

### Messenger (очереди)

| Переменная | Описание | По умолчанию |
|---|---|---|
| `MESSENGER_TRANSPORT_DSN` | DSN транспорта очередей | `doctrine://default` |

### Mailer

| Переменная | Описание | По умолчанию |
|---|---|---|
| `MAILER_DSN` | DSN для отправки почты | SMTP Яндекс |

### Rate Limiter

| Переменная | Описание | По умолчанию |
|---|---|---|
| `RATE_LIMIT_TOKENS` | Количество потребляемых токенов за запрос | `1` |

### Lock

| Переменная | Описание | По умолчанию |
|---|---|---|
| `LOCK_DSN` | DSN для блокировок | `flock` |

---

## 📊 Логирование

Проект использует Monolog с разделением по каналам:

| Канал | Файл (dev) | Файл (prod) | Назначение |
|---|---|---|---|
| `app` (основной) | `var/log/dev.log` | `stderr` (JSON) | Системные ошибки и отладка |
| `request` | `var/log/requests.log` | `var/log/requests.log` (JSON) | Логирование всех входящих/исходящих HTTP-запросов |
| `deprecation` | — | `stderr` (JSON) | Предупреждения о deprecations |

### Event Listener `RequestLoggerListener`

Автоматически логирует:
- **Входящие запросы**: метод, URI, IP, User-Agent, тело запроса
- **Исходящие ответы**: HTTP-статус, тело ответа

Просмотр логов запросов:
```bash
docker compose exec php tail -f var/log/requests.log
```

---

## 🛠️ Дополнительные команды

```bash
# Создание новой миграции после изменения сущностей
docker compose exec php bin/console make:migration

# Статус миграций
docker compose exec php bin/console doctrine:migrations:status

# Сброс и пересоздание БД (dev only!)
docker compose exec php bin/console doctrine:database:drop --force
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# Остановка всех контейнеров
docker compose down

# Остановка с удалением томов (сброс БД!)
docker compose down -v

# Проверка и фикс code-style (.editorconfig)
# Используется .editorconfig — настройте свою IDE
```

---

## 📄 Лицензия

MIT License — см. файл [LICENSE](LICENSE).

---

## 🔗 Полезные ссылки

- [Symfony документация](https://symfony.com/doc/current/index.html)
- [FrankenPHP документация](https://frankenphp.dev/docs/)
- [OpenAI Moderation API](https://platform.openai.com/docs/guides/moderation)
- [Symfony Messenger](https://symfony.com/doc/current/messenger.html)
- [Symfony Rate Limiter](https://symfony.com/doc/current/rate_limiter.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/current/)
- [NelmioApiDocBundle](https://github.com/nelmio/NelmioApiDocBundle)
- [Mailpit (тестирование почты)](https://mailpit.axllent.org/)
