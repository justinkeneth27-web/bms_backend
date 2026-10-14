# Barangay DRRM Disaster Operations Console - Laravel + React + PostgreSQL

This backend package was generated to convert the **Barangay DRRM Disaster Operations Console** to **Laravel (PHP) + React.js + PostgreSQL**.

---

## 🚀 Quick Start (Local Setup)

### Prerequisites
* **PHP >= 8.2** with `pdo_pgsql`, `pgsql`, `mbstring`, `openssl` extensions enabled
* **Composer**
* **PostgreSQL >= 14** (local, Supabase, Neon, or Cloud SQL)
* **Node.js >= 18**

---

### Step 1: Configure Database & Environment
1. Copy the environment file:
   ```bash
   cp .env.example .env
   ```
2. Open `.env` and set your PostgreSQL credentials:
   ```ini
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=barangay_drrm
   DB_USERNAME=postgres
   DB_PASSWORD=your_postgres_password
   ```

3. Create the database in PostgreSQL if it doesn't exist yet:
   ```sql
   CREATE DATABASE barangay_drrm;
   ```

---

### Step 2: Install Dependencies & Generate Key
```bash
composer install
php artisan key:generate
```

---

### Step 3: Run Migrations & Seeders
Run the automated PostgreSQL migrations and seed initial barangay data (officials, evacuation centers, households, audit logs):
```bash
php artisan migrate --seed
```

*(Alternatively, you can import `database/schema.sql` directly into pgAdmin, Supabase, Neon, or psql)*:
```bash
psql -U postgres -d barangay_drrm -f database/schema.sql
```

---

### Step 4: Start the Laravel API Server
```bash
php artisan serve
```
The API is now running at `http://127.0.0.1:8000/api/v1/`.

---

## 🐳 Docker / Laravel Sail (Zero Configuration)
If you have Docker installed, you can start Laravel + PostgreSQL in one command:
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

---

## 📡 API Endpoints Overview

### Postman
Import [`Barangay-DRRM-API.postman_collection.json`](./Barangay-DRRM-API.postman_collection.json) into Postman. The collection defaults to `http://127.0.0.1:8000/api/v1`; change its `baseUrl` collection variable if your API is hosted elsewhere. Login stores the issued Sanctum token in the `token` variable for the protected logout and current-user requests. Update the `barangay`, `householdId`, and `centerId` collection variables to target your records. The collection includes sample JSON bodies and filters for the available endpoints; most routes are currently public, while logout and current-user require the bearer token.

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/login` | Official login with Sanctum bearer token |
| `POST` | `/api/auth/security-question` | Retrieve security question for password recovery |
| `POST` | `/api/auth/reset-password` | Verify answer and reset password |
| `GET` | `/api/v1/households` | List households with filters (`barangay`, `purok`, `status`, `search`) |
| `POST` | `/api/v1/households` | Register new household record |
| `PATCH`| `/api/v1/households/{id}/status` | Update alert status (`SAFE`, `MODERATE`, `EMERGENCY`) |
| `DELETE`| `/api/v1/households/{id}` | Purge household record with audit log |
| `GET` | `/api/v1/evacuation-centers` | List centers with live occupancy and facilities |
| `POST` | `/api/v1/evacuation-centers/{id}/assign` | Assign household to center |
| `GET` | `/api/v1/relief-distributions` | List relief logs |
| `POST` | `/api/v1/relief-distributions` | Log relief distribution |
| `GET` | `/api/v1/activity-logs` | Operational audit trail with shift detection |
| `GET` | `/api/v1/activity-logs/export-csv` | Formatted CSV audit export with UTF-8 BOM |

---

## 🔗 Connecting the React Frontend
Set `VITE_LARAVEL_API_URL=http://localhost:8000/api/v1` in your React frontend `.env` file to immediately connect the React UI to this Laravel + PostgreSQL API!
