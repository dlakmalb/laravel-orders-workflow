<p align="center">
  <a href="https://laravel.com" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
  </a>
</p>

# 🧾 Laravel Order Workflow

This project demonstrates an **asynchronous order processing workflow** built with Laravel queues, Redis, and Horizon.  
It covers order import, processing, notifications, refunds, and real-time analytics.

---

## ⚙️ Requirements

- **PHP 8.2+**
- **Laravel 12**
- **MySQL** (for core data)
- **Redis** (for queues, caching, sessions, KPIs, and leaderboards)
- **Laravel Horizon** (for queue monitoring)

---

## 🧱 Setup

```bash
# Clone the repository
git clone https://github.com/dlakmalb/laravel-orders-workflow.git
cd laravel-orders-workflow

# Install dependencies
composer install

# Copy and configure environment
cp .env.example .env
php artisan key:generate

# Update .env for Redis:
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis

# Run migrations
php artisan migrate
```
## 🚀 Usage

1️⃣ Import Orders

```bash
# Import orders from a CSV file
php artisan orders:import storage/orders_sample.csv
```

2️⃣ Main Queue Worker

```bash
# Start the main queue worker. Each order goes through,
# Reserve stock → Simulate payment → Callback → Finalize or rollback
php artisan queue:work
```

3️⃣ Notifications Queue Worker

```bash
# After processing, notifications are queued separately and logged to notification_logs
php artisan queue:work --queue=notifications
```

4️⃣ Refunds

Start Queue Worker.
```bash
php artisan queue:work --queue=refunds
```

Process full or partial refunds asynchronously.
```bash
php artisan orders:refund {order_id} {amount_cents} [--reason="text"] [--key="unique-id"]

# Example
php artisan orders:refund 24 5000 --reason="Partial refund"
```

5️⃣ KPIs & Leaderboard

KPI keys in Redis.
```bash
kpi:YYYY-MM-DD:revenue_cents
kpi:YYYY-MM-DD:order_count
kpi:YYYY-MM-DD:avg_order_value_cents
```

Leaderboard key.
```bash
leaderboard:customers
```

Check in Tinker.
```bash
php artisan tinker
>>> Redis::get('kpi:2025-10-27:revenue_cents');
>>> Redis::get('kpi:2025-10-27:order_count');
>>> Redis::get('kpi:2025-10-27:avg_order_value_cents');
>>> Redis::zrevrange('leaderboard:customers', 0, 9, 'WITHSCORES');
```

6️⃣ Horizon Dashboard
```bash
# Monitor queues in real-time
php artisan horizon

# Visit:
http://orders-workflow.test/horizon
```
