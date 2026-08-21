# AGENTS.md

## Project Overview
Laravel 12 (Web) + FastAPI (BERT Inference Service).

## Development
- **Web**: `composer run dev`
- **BERT Service**:
  - Located in `bert-service/`.
  - Use Python 3.10+, virtual environment: `.\.venv\Scripts\Activate.ps1`.
  - Start: `fastapi dev app\main.py --port 8001`.
- **Database**: `php artisan migrate`.
- **Queue**: Run `php artisan queue:work` for asynchronous tasks.

## Admin
- Filament admin panel: `/admin`.
- User creation: `php artisan make:filament-user` (manually set `is_admin=1` in DB).
