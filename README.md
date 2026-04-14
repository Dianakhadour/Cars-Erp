#  Laravel Multi-Tenancy API with Sanctum Authentication

This project is a **Laravel-based multi-tenant API**, where each tenant has its own database.  
Authentication is handled using **Laravel Sanctum** with **UUID-based users and personal access tokens**.

---

##  Features

-  **Multi-tenancy using database-per-tenant strategy**
-  **Automatic tenant identification via domain or middleware**
-  **User authentication with Laravel Sanctum**
-  **UUID support for `users` & `personal_access_tokens`**
-  **Tenant-specific migrations**

---

##  Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 10 / 11 / 12 (based on your setup) |
| Auth | Laravel Sanctum |
| Database | MySQL / MariaDB |
| Multi-Tenancy | Custom Middleware / TenantResolver |
| UUID Support | `Str::uuid()` |

---

## Installation

```bash
git clone https://github.com/alaamohd2099-prog/carerp.git
cd carerp

# Install dependencies
composer install

# Create .env file
cp .env.example .env

# Generate app key
php artisan key:generate

# Configure DB_CONNECTION for landlord (main database)
# Configure tenant DB credentials as needed

# Run landlord migrations
php artisan migrate


##   Sanctum Authentication

### Register

```
http
POST /api/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password"
}
 
###Login
POST /api/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password"
}

Response:

{
     "token": "2|oJJNnpmyKEO4PvnrxPTSqwmuJhSKg11toBAyBx4x2ef82b58"
}

###Authenticated Endpoint

GET /api/profile
Authorization: Bearer {token}
Accept: application/json

Response 
{
    "id": "ceaf04ce-2d0b-44b7-9170-dceb646e48c5",
    "name": "John Doe",
    "email": "john@example.com"
}
###Project Structure
app/
 ├── Http/Middleware/
 │     ├── TenantResolver.php           # Detects tenant & switches DB
 │     └── HandleApiUnauthenticated.php # Handles unauthorized API responses
 ├── Models/
 │     ├── Tenant.php                   # Tenant model
 │     ├── User.php                     # Uses UUID & Sanctum
 │     └── PersonalAccessToken.php      # Overrides Sanctum token model for UUID
database/
 ├── migrations/                        # Landlord (main DB) migrations
 ├── migrations_tenant/                # Tenant-specific migrations
routes/
 ├── api.php                           # Auth + Protected Routes
 ├── web.php
config/
 ├── sanctum.php
 ├── auth.php




---

##   How Tenant Resolution Works

Each incoming request is matched to a tenant **based on the domain/subdomain**.  
This is handled by `App\Http\Middleware\TenantResolver`.

**Example Logic:**

```
php
$host = $request->getHost();

$tenant = Tenant::query()
    ->whereHas('domains', fn($q) => $q->where('domain', $host))
    ->first();

Config::set('database.connections.tenant.database', $tenant->database_name);
DB::purge('tenant');
DB::reconnect('tenant');


```

###Tenant Creation & Domain Setup

How Tenant Resolution Works

Every incoming request is checked by TenantResolver:

1 -Get the current domain from the request.

2-Find the matching tenant in the master tenants table`.

3-If found → Switch database connection to database_name.

4-All models with protected $connection = 'tenant'; now use their tenant DB.
Example

Request: http://tenant3.test/api/login
⇩
TenantResolver detects "tenant3.test"
⇩
Switches DB to "tenant_third_tenant"
⇩
Sanctum login happens inside tenant DB



API Authentication Flow (Postman / API Clients)


| Method | URL                                | Body                        | Auth Required  |
| ------ | ---------------------------------- | --------------------------- | -------------- |
| `POST` | `http://tenant3.test/api/register` | `{ name, email, password }` | ❌              |
| `POST` | `http://tenant3.test/api/login`    | `{ email, password }`       | ❌              |
| `GET`  | `http://tenant3.test/api/profile`  | —                           | ✅ Bearer Token |
| `POST` | `http://tenant3.test/api/logout`   | —                           | ✅ Bearer Token |


Use header:
Authorization: Bearer <token>
Accept: application/json
<!------------------------------------------------------------------------------------------------------------>
Creating a New Tenant (Automatic — No Manual Database Creation)

New tenants are provisioned using the existing Tenant, TenantDomain, and TenantProvision logic.

When a tenant is created 

1- A record is inserted into the master tenants table.

2- Its domain is stored in the tenant_domains table.

3- The TenantProvision service automatically creates a new database using the tenant's database_name.

The tenant migrations are executed against that new database.

 No need to manually CREATE DATABASE in MySQL.
 No need to manually insert tenant domain records.

After this, the tenant is fully active and accessible via
http://tenant3.test/api/login

###How to Run the Tenant Provisioning Command
From your project root, run:
 php artisan tenant:provision "Tenant Name" your-domain.test

✅ Examples
Command	Result
php artisan tenant:provision "Tenant One" tenant1.test	Creates database tenant_tenant_one, maps it to domain tenant1.test
php artisan tenant:provision "Acme Corp" acme.localhost	Creates database tenant_acme_corp, maps it to domain acme.localhost

then run 
php artisan serve --host=your-domain.test --port=8000 
for Run the project 