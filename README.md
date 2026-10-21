# Hostel Ballot API

Backend API for the hostel ballot system. It manages hostels, blocks, rooms and beds, and lets admins and students log in with their admin ID or student ID.

Built with **Laravel** and **MySQL**, using **Laravel Sanctum** for token authentication. The frontend is a separate **Next.js** project (`hostel-ballot`) that calls this API.

> **Status:** work in progress. Login, admin protection, room stats, the block overview and the filterable room list are done. Bed allocation and the ballot itself are not built yet.

## Requirements

- PHP 8.2 or higher
- Composer
- MySQL or MariaDB (developed on XAMPP, MariaDB 10.4)
- Laravel version: _fill in (run `php artisan --version`)_

## Setup

1. Clone the project and install dependencies:
    ```bash
    composer install
    ```
2. Copy the environment file and generate the app key:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
3. Create an empty database named `hostel_ballot`, then import `database/hostel_ballot.sql` (phpMyAdmin → Import). This creates the tables **and** the `room_status_view` view.
4. Open `.env` and set the database details. Check your MySQL port, because XAMPP sometimes uses `3307` instead of `3306`:

    ```
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=hostel_ballot
    DB_USERNAME=root
    DB_PASSWORD=

    SESSION_DRIVER=file
    CACHE_STORE=file
    QUEUE_CONNECTION=sync
    ```

5. Create the Sanctum tokens table:
    ```bash
    php artisan migrate
    ```
6. Start the server:
    ```bash
    php artisan serve
    ```
    The API runs at `http://127.0.0.1:8000/api`.

## Important: how the database works

Most tables were **created by hand in MySQL, not by Laravel migrations**. The SQL file in `database/` is the source of truth for the structure.

- **Never run `php artisan migrate:fresh` or `migrate:rollback`.** They drop tables, including the hand-made ones.
- The default Laravel migrations for `users`, `cache` and `jobs` were deleted on purpose. The only migration left is `personal_access_tokens`.
- In that migration, `tokenable_id` is a string (`varchar(10)`), not a number, because user IDs are strings like `U001`.
- If you change the database structure, export it again (structure only) and replace `database/hostel_ballot.sql`.

### Tables

| Table              | Purpose                                                                                  |
| ------------------ | ---------------------------------------------------------------------------------------- |
| `users`            | Login accounts (`user_id`, `email`, `password_hash`, `role`)                             |
| `admins`           | Admin profile, one per admin user (`admin_id`)                                           |
| `students`         | Student profile, one per student user (`student_id`)                                     |
| `hostel_details`   | Hostels                                                                                  |
| `blocks`           | Blocks in a hostel. **Gender is stored here** (`gender_type`), because hostels are mixed |
| `rooms`            | Rooms in a block. `room_type` is `S` (4 beds) or `L` (6 beds)                            |
| `beds`             | Beds in a room. **Status lives here only**                                               |
| `room_status_view` | View that works out each room's status from its beds                                     |

All primary keys are `varchar(10)` strings that you supply yourself (for example `H001`, `B001`, `R001`). They are not auto-incremented. Admin IDs and student IDs must never overlap, because login looks up both.

### Bed and room status

A bed's `status` is one of `Available`, `Allocated`, `Restricted`, `Utility`. Rooms have no status column. A room's status is calculated in `room_status_view`:

| Room status | Rule                                                          |
| ----------- | ------------------------------------------------------------- |
| Available   | At least one bed is `Available`                               |
| Allocated   | No `Available` beds, at least one `Allocated`                 |
| Restricted  | No `Available` or `Allocated` beds, at least one `Restricted` |
| Utility     | All beds are `Utility`                                        |
| No Beds     | The room has no beds yet                                      |

To change these rules, edit the view (`CREATE OR REPLACE VIEW room_status_view ...`) and update `database/hostel_ballot.sql`. The controllers read the view, so they need no changes unless a status name changes.

Ground floor is floor `1`. Floors run from `1` to `blocks.total_floors`.

## Authentication

- Users log in with their **admin ID or student ID** plus password, not email.
- Login returns a Sanctum token. Send it on every protected request:
    ```
    Authorization: Bearer <token>
    Accept: application/json
    ```
- Always send `Accept: application/json`, or Laravel may return an HTML page instead of a JSON error.
- Roles are `admin` and `student`. The `admin` middleware (`app/Http/Middleware/AdminOnly.php`) returns `403` to anyone else.

### Creating a user

Passwords must be bcrypt hashes. Generate one:

```bash
php artisan tinker
```

```php
echo Illuminate\Support\Facades\Hash::make('password123');
```

Copy the hash without any backslashes (tinker escapes `$` as `\$` when it prints a value without `echo`). Then insert the rows:

```sql
INSERT INTO users (user_id, email, password_hash, role)
VALUES ('U001', 'admin@example.com', 'PASTE_HASH_HERE', 'admin');

INSERT INTO admins (admin_id, user_id, first_name, last_name)
VALUES ('A001', 'U001', 'First', 'Last');
```

A valid hash starts with `$2y$` and is exactly 60 characters long. Check with:

```sql
SELECT email, LENGTH(password_hash) AS len, LEFT(password_hash, 4) AS prefix FROM users;
```

## API endpoints

Base URL: `http://127.0.0.1:8000/api`

| Method | Endpoint                  | Access    | Description                                                                         |
| ------ | ------------------------- | --------- | ----------------------------------------------------------------------------------- |
| POST   | `/login`                  | Public    | Body: `id`, `password`. Returns `token` and `user`                                  |
| GET    | `/me`                     | Logged in | Current user with their admin or student profile                                    |
| POST   | `/logout`                 | Logged in | Deletes the current token                                                           |
| GET    | `/hostels`                | Admin     | Hostels with blocks, rooms and beds                                                 |
| GET    | `/stats/rooms`            | Admin     | Total, available, allocated and restricted room counts                              |
| GET    | `/blocks`                 | Admin     | All blocks with a quick room summary (available, allocated, restricted)             |
| GET    | `/blocks/{number}`        | Admin     | One block: gender, floors, every room and its status, plus block totals             |
| GET    | `/blocks/{number}/floors` | Admin     | One row per floor: room counts by status, and small/large room counts               |
| GET    | `/rooms`                  | Admin     | Filterable, paginated room list (see below)                                         |

Blocks are looked up by **block number**. Block numbers are only unique within a hostel. If two hostels share a number, the API returns `409`, and you add `?hostel_id=H001` to pick one.

### Room list filters

`GET /rooms` accepts these optional query parameters. Filters combine with AND, and a filter that is left out (or set to `all`) is skipped.

| Parameter   | Values                                                    | Description                                         |
| ----------- | --------------------------------------------------------- | --------------------------------------------------- |
| `block`     | A block number, or `all`                                  | Rooms in that block                                 |
| `floor`     | A floor number, or `all`                                  | Rooms on that floor                                 |
| `status`    | `available`, `allocated`, `restricted`, `utility`, `all`  | The room's overall status. Anything else gives `422` |
| `search`    | Digits                                                    | Room numbers that start with this value             |
| `hostel_id` | A hostel ID                                               | Only needed if two hostels share a block number     |
| `per_page`  | 1 to 100 (default 20)                                     | Page size                                           |
| `page`      | A page number                                             | Which page to return                                |

Example: `GET /rooms?block=1&floor=2&status=available&search=2`

Each room in `data` has `room_number`, `block_number`, `floor_number`, `gender_type`, `room_type`, `capacity`, `allocated_beds`, `available_beds` and `room_status`. Pagination info (`current_page`, `last_page`, `total`) comes with it. When going to another page, send the same filters again.

## Frontend

The Next.js app (`hostel-ballot`) is a separate project with its own `.env.local`:

```
API_URL=http://127.0.0.1:8000/api
```

- The login runs on the Next.js **server**. It calls `POST /login`, then stores the token in an **httpOnly cookie**. The token is never put in `localStorage` and browser JavaScript cannot read it.
- Pages and server actions read the cookie and send it to this API as the Bearer token.
- Client components cannot call this API directly. Use a Next.js server component or route handler to forward the request.
- Start both projects: `php artisan serve` (port 8000) and `npm run dev` (port 3000).

## Code layout

```
app/
  Http/Controllers/
    AuthController.php       login, me, logout
    BlockController.php      block list, detail, floors overview
    DashboardController.php  room stats
    RoomController.php       filterable room list
  Http/Middleware/
    AdminOnly.php            blocks non-admin users
  Models/
    StringKeyModel.php       base class: string keys, no timestamps
    User, Admin, Student, HostelDetail, Block, Room, Bed
routes/api.php               all API routes
database/hostel_ballot.sql   database structure (tables and view)
```

Because the tables use custom key names, every relationship in the models spells out its foreign key and local key. This is why they look different from most Laravel tutorials. The block, room and stats endpoints read `room_status_view` instead of the models, so the status rules live in one place.

## Testing the API

Use Postman or Thunder Client.

1. `POST /api/login` with `{"id": "A001", "password": "password123"}`.
2. Copy the token and set it under Authorization → Bearer Token.
3. Call any protected endpoint. A student token gets `403` on the admin routes.

## Troubleshooting

| Problem                                           | Likely cause                                                                                                                   |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `This password does not use the Bcrypt algorithm` | `password_hash` is not a valid bcrypt hash. Re-generate it and check for stray `\` characters                                  |
| `Table 'room_status_view' doesn't exist`          | The view was not created. Import `database/hostel_ballot.sql` or run the `CREATE VIEW` statement                               |
| Allocated counts are always 0                     | The view is out of date and still produces an old status name. Re-run the `CREATE OR REPLACE VIEW` from `database/hostel_ballot.sql` |
| `401` instead of data                             | Missing or wrong Bearer token, or missing `Accept: application/json`                                                           |
| `403 Forbidden`                                   | The logged-in user is not an admin                                                                                             |
| `422 Invalid status` on `/rooms`                  | `status` is not one of the allowed values listed above                                                                         |
| Cannot connect to the database                    | MySQL is not running, or `DB_PORT` in `.env` does not match XAMPP's port. Run `php artisan config:clear` after changing `.env` |

## Not built yet

- Bed allocation and the ballot (nothing records which student holds which bed)
- Student-facing endpoints
- Creating and editing hostels, blocks, rooms, beds and users through the API (currently done directly in the database)
- Login rate limiting, token expiry and change-password

## Deployment notes

- Set the real database details in the server's `.env`. Never commit `.env`.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- In `config/cors.php`, allow only the real frontend URL, not `*`.
- Check the server's PHP version against the Laravel version in `composer.json`.
