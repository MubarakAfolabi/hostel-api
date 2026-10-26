# Hostel Ballot API

Backend API for the hostel ballot system. It manages hostels, blocks, rooms and beds, and lets admins and students log in with their admin ID or student ID.

Built with **Laravel** and **MySQL**, using **Laravel Sanctum** for token authentication. The frontend is a separate **Next.js** project (`hostel-ballot`) that calls this API.

> **Status:** work in progress. Login, admin protection, room stats, the block overview, the filterable room list and the hostel data are done. Bed allocation and the ballot itself are not built yet.

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
3. Create an empty database named `hostel_ballot`, then import `database/hostel_ballot.sql` (phpMyAdmin → Import). This creates the tables, the `room_status_view` view, and the triggers and stored procedure that keep room status up to date. Then load the hostel data (see "Loading the hostel data" below).
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
| `rooms`            | Rooms in a block. **Status lives here.** `room_type` is `S` (4 beds), `L` (6 beds) or `Other` |
| `beds`             | Beds in S and L rooms. Status is `Available` or `Allocated`, and `student_id` says who holds it |
| `room_status_view` | View used by the API: each room with its status and its available and allocated bed counts |

All primary keys are `varchar(10)` strings that you supply yourself (for example `H001`, `B001`, `R001`). They are not auto-incremented. Admin IDs and student IDs must never overlap, because login looks up both.

### Room and bed status

**Rooms** hold the room status, in `rooms.status`:

| Room status | Meaning                                                               |
| ----------- | --------------------------------------------------------------------- |
| Available   | An S or L room with at least one free bed                             |
| Allocated   | An S or L room where every bed is taken (the room is full)            |
| Utility     | Common rooms. They have no beds                                       |
| Restricted  | Store, offices and other non-bedrooms. They have no beds              |

**Beds** only hold `Available` or `Allocated`, plus `student_id`:

- A bed is `Allocated` exactly when it has a student, and `Available` when `student_id` is empty. The database rejects any bed that has only one of the two.
- A student can hold only one bed.
- `Utility` and `Restricted` rooms (`room_type` = `Other`) have capacity 0 and no beds.

**Triggers keep the room status correct.** Whenever a bed is added, changed or removed, the stored procedure `refresh_room_status` recalculates the status of that room. This happens inside the database, so it also works when someone edits a bed in phpMyAdmin. The triggers only touch rooms whose status is `Available` or `Allocated`, so `Utility` and `Restricted` rooms keep the status you set.

To allocate or free a bed, update **only the bed**:

```sql
UPDATE beds SET student_id = 'S001', status = 'Allocated' WHERE bed_id = 'R0007-1';
UPDATE beds SET student_id = NULL, status = 'Available' WHERE bed_id = 'R0007-1';
```

**Limit to remember:** MySQL does not allow a statement to read the `rooms` table while a trigger it fires is updating it. An update to `beds` that also reads `rooms` (for example `WHERE room_id IN (SELECT room_id FROM rooms ...)`) fails with error 1442. Update beds by their own ID, which is what Eloquent does by default.

Ground floor is floor `1`. Floors run from `1` to `blocks.total_floors`.

## Loading the hostel data

The hostel, blocks, rooms and beds come from the 2026 hostel profile spreadsheet. Two scripts in `database/` load them:

1. `schema_update.sql` adds `rooms.status`, `beds.student_id`, the new view and the triggers. **Run it once** on a database that does not have them yet. (If you imported the current `hostel_ballot.sql`, the schema is already up to date and you can skip this.)
2. `seed_hostel.sql` loads the data:
   - 1 hostel (`H001`), 11 blocks (`B001` to `B011`; block 9 is Wing A, block 10 is Wing B, block 11 is OPH)
   - 584 rooms and 2,680 beds, all beds `Available`
   - Blocks 1, 4, 5, 9, 10 and 11 are female, blocks 2, 3, 6, 7 and 8 are male
   - Room IDs are `R0001` to `R0584`, and bed IDs look like `R0007-1`

**Do not re-run `seed_hostel.sql` once students have beds.** It deletes every bed under `H001`, so real allocations would be lost. Room numbers are stored as numbers, so `001` is stored as `1`.

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
| `status`    | `available`, `allocated`, `restricted`, `utility`, `all`  | The room's status (from `rooms.status`). Anything else gives `422` |
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

Because the tables use custom key names, every relationship in the models spells out its foreign key and local key. This is why they look different from most Laravel tutorials. The block, room and stats endpoints read `room_status_view`, which reads `rooms.status`. The triggers in the database keep that status correct.

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
| Allocated counts are always 0                     | No beds are allocated yet, or the view is out of date. Re-run the `CREATE OR REPLACE VIEW` from `database/hostel_ballot.sql` |
| `#1558 Column count of mysql.proc is wrong`       | The MariaDB system tables are from an older version. Run `mysql_upgrade` (for XAMPP on Linux: `sudo /opt/lampp/bin/mysql_upgrade -u root`), restart MySQL and retry |
| `1442 Can't update table 'rooms' in stored function/trigger` | A statement on `beds` also reads `rooms`. Update beds by their own ID, or read the room IDs first |
| A room's status looks wrong                       | The triggers are missing. Run `SHOW TRIGGERS;`. There should be `beds_after_insert`, `beds_after_update` and `beds_after_delete` |
| `401` instead of data                             | Missing or wrong Bearer token, or missing `Accept: application/json`                                                           |
| `403 Forbidden`                                   | The logged-in user is not an admin                                                                                             |
| `422 Invalid status` on `/rooms`                  | `status` is not one of the allowed values listed above                                                                         |
| Cannot connect to the database                    | MySQL is not running, or `DB_PORT` in `.env` does not match XAMPP's port. Run `php artisan config:clear` after changing `.env` |

## Not built yet

- The ballot and the allocation endpoints. The database can already record which student holds which bed (`beds.student_id`), but nothing in the API assigns beds yet
- Student-facing endpoints
- Creating and editing hostels, blocks, rooms, beds and users through the API (currently done directly in the database)
- Login rate limiting, token expiry and change-password

## Deployment notes

- Set the real database details in the server's `.env`. Never commit `.env`.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- In `config/cors.php`, allow only the real frontend URL, not `*`.
- Check the server's PHP version against the Laravel version in `composer.json`.
