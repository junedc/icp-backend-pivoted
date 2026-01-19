# Laravel Sanctum Ordering API

Features:
- Categories (CRUD)
- Products (CRUD, stock tracking)
- Orders (Pending / Completed / Cancelled)
- Stock deduction on order
- Restock on cancel
- Sanctum authentication
- PHPUnit feature tests

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link (for image)
```


## Running
```bash
sail up 
