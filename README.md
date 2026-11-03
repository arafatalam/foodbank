# FoodBank: Local Setup Guide

This guide takes you from downloading the project to signing in with a demo account. It is for running FoodBank on your own computer for development.

Run commands in a terminal. Unless a step says otherwise, run them from inside the `foodbank` folder.

## 1. Install the required tools

You need:

| Tool | Purpose |
|---|---|
| Git | Downloads the project |
| PHP 8.5 | Runs the Laravel application |
| Composer 2 | Installs the project's PHP packages |
| Node.js 22.18 or a newer 22.x version, with npm | Runs the Vue and Tailwind development tools |
| MySQL | Stores the application's records |

PHP needs the MySQL driver, usually called `pdo_mysql`.

**Why PHP 8.5?** The project allows PHP 8.3 for the application, but its locked development packages need at least PHP 8.4.1. PHP 8.5 matches the project's automated checks.

Check your installed versions:

```bash
# Check that each tool is available.
git --version
php --version
composer --version
node --version
npm --version
mysql --version
```

If a command is unavailable, install that tool before continuing. MySQL may also be managed through a desktop database application.

## 2. Download the project

```bash
# Download the repository.
git clone https://github.com/arafatalam/foodbank.git

# Move into the downloaded folder.
cd foodbank
```

If the repository is private, GitHub will require an account with access.

## 3. Install the PHP packages

The repository contains the application code. Composer downloads the supporting packages it needs.

```bash
# Install the versions recorded in composer.lock.
composer install
```

Use `install` for the initial setup so you receive the project's recorded package versions.

## 4. Create your local settings file

Laravel reads settings such as database credentials from a file named `.env`.

```bash
# Make your own settings file from the supplied example.
cp .env.example .env
```

Do this once on a fresh clone. If `.env` already exists, keep it and edit its settings.

Open `.env` in your editor. Set the application details:

```dotenv
# Settings for running the application on your computer.
APP_NAME=FoodBank
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
```

Keep `.env` private. It can contain passwords and should not be committed to Git.

## 5. Create the database

Start MySQL using your computer's database tools.

Connect through a database application or the terminal:

```bash
# Sign in to MySQL; enter your local MySQL password when asked.
mysql -u root -p
```

Inside MySQL, run:

```sql
-- Create an empty database for this local copy.
CREATE DATABASE foodbank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

If you used the terminal, leave MySQL with:

```sql
-- Return to your normal terminal.
exit;
```

Now update `.env` to match your MySQL connection:

```dotenv
# Replace the username and password with your local MySQL credentials.
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=foodbank
DB_USERNAME=root
DB_PASSWORD=
```

An empty `DB_PASSWORD` works only if your MySQL account has no password. Put passwords containing spaces or special characters inside double quotes.

## 6. Generate the application key

Laravel uses this key to protect encrypted information, including session cookies.

```bash
# Generate a key and save it in .env.
php artisan key:generate
```

Generate it once for this new installation.

## 7. Create the tables and sample records

A **migration** describes a database table. A **seeder** adds starting records.

```bash
# Create the database tables.
php artisan migrate

# Add roles, accounts, catalogue records, and fictional demo data.
php artisan db:seed
```

The main seeder runs the individual seeders in the required order.

Keep `APP_ENV=local`: some demo seeders deliberately refuse to run outside local development or testing.

## 8. Install the frontend packages

These packages provide Vue, Tailwind, and the tools that prepare the browser files.

```bash
# Install the versions recorded in package-lock.json.
npm ci
```

## 9. Start the application

Use two terminal windows, both inside the `foodbank` folder.

**Terminal 1 — Laravel:**

```bash
# Start the application server.
php artisan serve
```

**Terminal 2 — Vue and Tailwind:**

```bash
# Start the frontend development server.
npm run dev
```

Leave both running while working on the project. Press `Ctrl+C` in each terminal when you want to stop.

Open [FoodBank locally](http://localhost:8000). If Laravel reports a different address because the port is busy, use the address it displays and update `APP_URL` to match.

## 10. Sign in

The seeded local accounts are:

| Role | Username | Password |
|---|---|---|
| Super Admin | `super.admin` | `super.admin` |
| Manager | `amina.rahman` | `amina.rahman` |
| Volunteer | `farah.khan` | `farah.khan` |

These are predictable demo credentials for local development.

The project currently disables public registration, so use a seeded account.

## 11. Check that setup worked

Confirm that:

1. The sign-in page opens and has styling.
2. You can sign in as the manager.
3. The dashboard opens.
4. The stock list displays sample records.

The example configuration stores outgoing email in `storage/logs/laravel.log`. It does not deliver messages to a real inbox.

## Starting it again tomorrow

You do not need to reinstall packages, generate another key, or seed the database each day.

Start MySQL, then run these in separate terminals:

```bash
# First terminal: start Laravel.
php artisan serve
```

```bash
# Second terminal: start the frontend tools.
npm run dev
```

Visit [FoodBank locally](http://localhost:8000).

## Common setup problems

| Problem | What to check |
|---|---|
| Database connection refused | MySQL must be running; check the host and port in `.env`. |
| Database access denied | Check the MySQL username and password in `.env`. |
| Unknown database `foodbank` | Create the database before running migrations. |
| Could not find driver | Enable PHP's `pdo_mysql` extension. |
| No application encryption key | Run `php artisan key:generate`. |
| Missing database table | Run `php artisan migrate`. |
| Vite manifest missing or frontend unavailable | Keep `npm run dev` running in the second terminal. |
| Composer reports an unsupported PHP version | Check that terminal PHP is version 8.5. |
| npm reports an unsupported Node version | Use Node.js 22.18 or a newer 22.x version. |

If Laravel keeps using old settings after you edit `.env`, run:

```bash
# Clear cached settings so Laravel reads .env again.
php artisan config:clear
```
