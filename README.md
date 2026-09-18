# Puean Chuay Tiu API

REST-like PHP API for the **Puean Chuay Tiu** university peer-tutoring Mini Project.

## Stack

- PHP 8 with PDO
- MySQL / MariaDB
- Apache (XAMPP is suitable for local development)

## Setup

1. Create the runtime environment file outside Apache's document root. For a default XAMPP installation, a suitable location is `C:\xampp\private\mini_backend.env`.
2. Copy the keys from `.env.example` into that external file and configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
3. Set `AUTH_SECRET` to a cryptographically random value of at least 32 characters. The API fails closed when this value is missing or too short.
4. Set `ALLOWED_ORIGINS` to the exact web origins allowed to call the API.
5. Set the process/Apache environment variable `MINI_BACKEND_ENV_FILE` to the absolute path of the external environment file, then restart Apache. With XAMPP this can be configured in Apache's environment configuration, for example with `SetEnv MINI_BACKEND_ENV_FILE "C:/xampp/private/mini_backend.env"` in a server configuration file outside `htdocs`.
6. Import `database/schema.sql` into an empty database.
7. Serve this directory through Apache/XAMPP.

The loader retains local `.env` support for compatibility, but external configuration is the recommended setup. The repository `.htaccess` denies HTTP access to all dotfiles, including `.env`, `.env.*`, `.auth_secret`, `.git`, and `.env.example`. The `.env.example` file remains allowed in Git as a key-only template and must never contain real values.

For a public demo, serve the API over HTTPS. Plain HTTP should only be used on a trusted local development network.

## API Overview

- Auth: `create_member.php`, `login.php`
- User: `get_user.php`, `update_member.php`
- Tutor: `create_tutor.php`, `get_tutor_list.php`
- Course: `get_course_options.php`, `get_tutor_courses.php`, `create_tutor_course.php`, `update_tutor_course.php`, `toggle_course_status.php`, `delete_tutor_course.php`
- Chat: `get_conversations.php`, `get_or_create_conversation.php`, `get_messages.php`, `send_message.php`

Protected endpoints require `Authorization: Bearer <token>`. Tokens expire after eight hours. Existing legacy plaintext passwords are upgraded to a secure hash after the next successful login; new and changed passwords are always hashed.

## Security and Repository Hygiene

Do not commit `.env`, `.env.*` (except `.env.example`), `.auth_secret`, logs, uploaded profile images, database exports containing real users, or credentials. The API never creates `.auth_secret`; it reads `AUTH_SECRET` only from the environment configuration. Existing tracked sensitive files must be removed from the index and repository history under the owner's supervision; see `GIT_HISTORY_CLEANUP_PLAN.md`.
