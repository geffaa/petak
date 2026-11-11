# Petak

A WebGIS for land parcels. Browse a map of parcels, filter by land use, status, price and area, search by radius around a point, and open any parcel for its details and statistics. *Petak* is Indonesian for "plot of land".

> Portfolio project. All parcel data is synthetic (seeded around Sleman, Yogyakarta).

## Stack

- **Backend:** Laravel API (`/api/v1`), PostgreSQL + **PostGIS**
- **Map data:** vector tiles generated in the database with `ST_AsMVT`, served with HTTP caching so panning stays fast
- **Frontend:** React + TypeScript + **MapLibre GL**, Vite
- **Tests:** PHPUnit feature tests against a PostGIS test database

## What it does

- Vector-tile parcel layer, styled by land use (residential, commercial, mixed use, industrial, agricultural)
- Filters: bounding box, land use, status, price per m², area, and radius search around a point
- Parcel detail panel and aggregate statistics for the current view
- Versioned, rate-limited JSON API; tiles are cached instead of rate-limited because they are requested in bursts

## Run it

```bash
cp .env.example .env
docker compose up -d          # PostGIS on host port 54329
composer install && npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve --port=8010
```

Tests: `php artisan test` (uses the `webgis_test` database).

## API

| Endpoint | Purpose |
|---|---|
| `GET /api/v1/map` | Map metadata |
| `GET /api/v1/parcels` | Filtered parcel list |
| `GET /api/v1/parcels/{id}` | One parcel |
| `GET /api/v1/parcels/statistics` | Aggregates for the current filters |
| `GET /api/v1/parcels/tiles/{z}/{x}/{y}.mvt` | Vector tiles |
