# Colligo Web

> An AI-powered educational web application built with Symfony 6

---

## About

**Colligo** is an AI-driven educational platform developed in PHP using the Symfony 6 framework. It aims to deliver an interactive and intelligent learning experience through a modern web interface powered by Twig templates.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP >= 8.0.2 |
| Framework | Symfony 6.0 |
| ORM | Doctrine ORM + Migrations |
| Templating | Twig |
| Messaging | Symfony Messenger |
| Security | Symfony Security Bundle |
| Mailer | Symfony Mailer |
| HTTP Client | Symfony HTTP Client |
| Containerization | Docker Compose |
| Testing | PHPUnit 9.5 |

---

## Project Structure

```
colligo-web/
├── bin/                  # Symfony console entry point
├── config/               # App configuration (routes, services, packages)
├── migrations/           # Doctrine database migrations
├── public/               # Web root (index.php, assets)
├── src/                  # Application source code (controllers, entities, services)
├── templates/            # Twig templates
├── tests/                # PHPUnit test suites
├── translations/         # i18n translation files
├── compose.yaml          # Docker Compose configuration
├── compose.override.yaml # Local Docker overrides
├── composer.json         # PHP dependencies
└── phpunit.xml.dist      # PHPUnit configuration
```

---

## Prerequisites

- PHP >= 8.0.2 with extensions: `ctype`, `iconv`
- [Composer](https://getcomposer.org/)
- [Symfony CLI](https://symfony.com/download) (recommended)
- Docker & Docker Compose (optional, for containerized setup)
- A database supported by Doctrine (e.g., MySQL, PostgreSQL, SQLite)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/SouissiOumaima/colligo-web.git
cd colligo-web
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Configure environment variables

Copy the example environment file and update it with your settings:

```bash
cp .env .env.local
```

Edit `.env.local` and set your database connection and any other required variables:

```env
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/colligo"
```

### 4. Set up the database

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 5. Start the development server

```bash
symfony server:start
```

Or using PHP's built-in server:

```bash
php -S localhost:8000 -t public/
```

---

## Docker Setup

A Docker Compose configuration is included for a containerized environment:

```bash
docker compose up -d
```

Then run the migrations inside the container:

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
```

---

## Running Tests

```bash
php bin/phpunit
```

Or with Symfony CLI:

```bash
symfony php bin/phpunit
```

---

## Useful Commands

| Command | Description |
|---|---|
| `php bin/console cache:clear` | Clear the application cache |
| `php bin/console doctrine:migrations:migrate` | Run pending database migrations |
| `php bin/console make:controller` | Generate a new controller |
| `php bin/console make:entity` | Generate a new entity |
| `php bin/console debug:router` | List all registered routes |

---

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m 'Add my feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

---

## License

This project is proprietary. All rights reserved.

---

## Author

**Souissi Oumaima** — [GitHub](https://github.com/SouissiOumaima)
