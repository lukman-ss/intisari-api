# Getting Started

Welcome to the Intisari API! This guide will help you set up the project locally.

## Prerequisites
- PHP >= 8.2
- Composer
- SQLite (or your preferred database)

## Installation

You can install the API via Composer:

```bash
composer create-project lukman-ss/intisari-api your-project-name
cd your-project-name
```

## Setup Environment

Copy the example environment file and configure it:

```bash
cp .env.example .env
```

Ensure the following variables are set for a local SQLite setup:
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Create the SQLite database file if it doesn't exist:
```bash
touch database/database.sqlite
```

## Running Migrations

Initialize the database schema by running the migrations:

```bash
php console.php migrate
```

## Running the Server

Start the local development server:

```bash
php -S localhost:8000 -t public
```

Your API is now running at `http://localhost:8000`. Test it by hitting the health check endpoint:

```bash
curl -X GET http://localhost:8000/api/health
```
