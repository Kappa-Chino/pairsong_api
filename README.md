<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# ふたり音 API仕様書
エラー処理が明記されていない箇所のエラーハンドリングの処理は不要とする。

## Base URL
https://event.jec.ac.jp/pairsong_api/api

## ルート一覧取得
* DBに登録されているルートの一覧を取得する

### リクエストURL
GET /route/{deviceId}/
* deviceId : ルートを登録したデバイス（Leafony）のID。検証では1を利用。

### リクエストBody
なし

### レスポンスBody（例）
#### 200 OK
```json
[
    {
    "id" : 1,
    "date" : "2025-10-01 01:23:45"
    },
    {
    "id" : 2,
    "date" : "2025-10-01 01:23:55"
    }
]
```
* date は、そのルートの起点（スタート地点の記録日時）とする

## ルート詳細取得
* 1件分のルート詳細を取得する

### リクエストURL
GET /route/{deviceId}/{routeId}/
* deviceId : ルートを登録したデバイス（Leafony）のID  ※使わないけどURL整理の都合上入れる
* routeId : 取得するルートのID

### リクエストBody
なし

### レスポンスBody（例）
#### 200 OK
```json
{
  "id" : 1,
  "name" : "福井県鯖江市",
  "routes" : [
    {
      "date": "2025-10-01 01:23:45",
      "lat" : 35.6983358649205,
      "lng" : 139.69812953474448
    },
    {
      "date": "2025-10-01 01:23:50",
      "lat" : 35.6983358649205,
      "lng" : 139.69812953474458
    },
    {
      "date": "2025-10-01 01:23:55",
      "lat" : 35.6983358649205,
      "lng" : 139.69812953474468
    },
    {
      "date": "2025-10-01 01:24:00",
      "lat" : 35.6983358649210,
      "lng" : 139.69812953474478
    }
  ]
}
```
* routes は時間の昇順ソート
* name スタート地点の住所にする。「福井県鯖江市」固定。

## 【新】最新ルート詳細取得
* 最後に登録されたルート詳細を取得する

### リクエストURL
GET /route/{deviceId}/{routeId}/latest
* deviceId : ルートを登録したデバイス（Leafony）のID  ※使わないけどURL整理の都合上入れる
* routeId : 取得するルートのID

### URLパラメーター
* date：YYYY-mm-dd形式で指定。指定した日付の中の最新データを取得する。
　該当の日にデータが存在しない場合、404 Not Foundを返す。

### リクエストBody
なし

### レスポンスBody（例）
#### 200 OK
```json
{
  "id" : 1,
  "name" : "福井県鯖江市",
  "routes" : [
    {
      "date": "2025-10-01 01:23:45",
      "lat" : 35.6983358649205,
      "lng" : 139.69812953474448
    },
    {
      "date": "2025-10-01 01:23:50",
      "lat" : 35.6983358649205,
      "lng" : 139.69812953474458
    },
    {
      "date": "2025-10-01 01:23:55",
      "lat" : 35.6983358649205,
      "lng" : 139.69812953474468
    },
    {
      "date": "2025-10-01 01:24:00",
      "lat" : 35.6983358649210,
      "lng" : 139.69812953474478
    }
  ]
}
```

#### 404 NotFound
```json
{
  "status" : 404,
  "message" : "Not Found"
}
```
仕様は「ルート詳細取得」APIの実装に合わせる。

## ルート登録
* ルートデータを登録する
* LeafonyがGPSで取得した位置情報をスマホ経由でまとめて登録する

### リクエストURL
POST /route/{deviceId}/
* deviceId : 登録するデータを所有するデバイス（Leafony）のID

### リクエストBody（例）
```json
[
  {
    "date" : "2025-10-31 10:00:00",
    "lat" : 35.6983358649205,
    "lng" : 139.69812953474448
  },
  {
    "date" : "2025-10-31 10:00:00",
    "lat" : 35.6983358649205,
    "lng" : 139.69812953474448
  },
  {
    "date" : "2025-10-31 10:00:00",
    "lat" : 35.6983358649205,
    "lng" : 139.69812953474448
  }
]
```

### レスポンスBody（例）
#### 200 OK
```json
{
  "status" : "success",
  "music_url" : "http://"
}
```


#なんか使えるかも
https://qiita.com/team_kuchibashi/items/bd58ab800067ae8e9664
# pairsong_api
