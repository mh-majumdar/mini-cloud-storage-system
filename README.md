# Mini Cloud Storage System

A simple backend API that simulates a cloud file storage service. Users can upload, delete, and view stored file metadata under a 500 MB storage quota.

Built with **Laravel 10** and **MySQL**.

---

## Setup Instructions

### Requirements
- PHP >= 8.1
- Composer
- MySQL

### Installation

1. Clone the repository:
```bash
git clone <repo-url>
cd cloud-storage
```

2. Install dependencies:
```bash
composer install
```

3. Copy environment file and set your database credentials:
```bash
cp .env.example .env
```
Edit `.env` and set your MySQL credentials:
```
DB_DATABASE=cloud_storage
DB_USERNAME=root
DB_PASSWORD=yourpassword
```

4. Generate app key:
```bash
php artisan key:generate
```

5. Create the database in MySQL:
```sql
CREATE DATABASE cloud_storage;
```

6. Run migrations and seed sample users:
```bash
php artisan migrate
php artisan db:seed
```

7. Start the development server:
```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api/`

---

## Database Design

### Tables

**users** — stores user accounts (3 sample users seeded)

**file_stores** — stores unique physical files identified by hash (for deduplication)

**files** — stores file ownership records per user, with soft deletion support

### ER Diagram

```
users (1) ----< (many) files (many) >---- (1) file_stores
```

Each user owns multiple file records. Multiple file records can point to the same file_store entry if the file hash matches (deduplication).

---

## API Endpoints

Base URL: `http://localhost:8000/api`

### 1. Upload File
```
POST /users/{user_id}/files
```

**Request Body (JSON):**
```json
{
    "file_name": "report.pdf",
    "file_size": 10485760,
    "file_hash": "abc123def456"
}
```
- `file_name` — name of the file (string, required)
- `file_size` — size in bytes (integer, required)
- `file_hash` — hash of file content (string, required)

**Success Response (201):**
```json
{
    "message": "File uploaded successfully.",
    "file": {
        "id": 1,
        "file_name": "report.pdf",
        "file_size": 10485760,
        "uploaded_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Error — Storage Exceeded (422):**
```json
{
    "error": "Storage limit exceeded. You have 200.5 MB remaining."
}
```

**Error — Duplicate Name (422):**
```json
{
    "error": "A file with this name already exists."
}
```

### 2. Delete File
```
DELETE /users/{user_id}/files/{file_id}
```

**Success Response (200):**
```json
{
    "message": "File deleted successfully."
}
```

**Error — Not Found (404):**
```json
{
    "error": "File not found."
}
```

### 3. Get Storage Summary
```
GET /users/{user_id}/storage-summary
```

**Response (200):**
```json
{
    "user_id": 1,
    "storage_limit": "500 MB",
    "total_used": "30 MB",
    "remaining": "470 MB",
    "total_used_bytes": 31457280,
    "remaining_bytes": 493391872,
    "total_active_files": 3
}
```

### 4. List User Files
```
GET /users/{user_id}/files
```

**Response (200):**
```json
{
    "user_id": 1,
    "files": [
        {
            "id": 1,
            "file_name": "report.pdf",
            "file_size": 10485760,
            "uploaded_at": "2024-01-15T10:30:00.000000Z"
        }
    ]
}
```

---


## Design Decisions

### Storage Quota
Each user has a fixed 500 MB limit. The limit is checked before every upload. File sizes are stored in bytes for precision.

### File Metadata Only
No actual file content is stored on disk — only metadata (name, size, hash, timestamp) is recorded in the database. This keeps things simple and focused on the business logic.

### Soft Deletes
Files are not physically removed from the database — they get a `deleted_at` timestamp. This makes it easy to track history, and deleted files automatically stop counting toward quota since all queries filter on `deleted_at IS NULL`.

### Deduplication
The `file_stores` table holds one row per unique `file_hash`. When multiple users upload files with the same hash, they share the same `file_store` record. A `ref_count` column tracks how many active file records point to each store entry. When the last reference is deleted, the store record is cleaned up.

### Concurrency Handling
The upload endpoint uses MySQL's `SELECT ... FOR UPDATE` inside a database transaction. Before inserting a new file, the query locks all of the user's active file rows. This prevents two concurrent uploads from both passing the storage check and then together exceeding the 500 MB limit. The lock is released when the transaction commits.

**How it works step by step:**
1. Begin transaction
2. Lock user's active files with `SELECT ... FOR UPDATE`
3. Check if file name already exists → reject if duplicate
4. Sum file sizes and check against 500 MB limit → reject if exceeded
5. Create file_store record (or reuse existing one by hash)
6. Insert the file record
7. Commit transaction and release locks

Any other upload request for the same user that arrives during this window will wait at step 2 until the first transaction finishes.
