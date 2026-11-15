# BookShelf 書籍レビューアプリ

## 概要

BookShelf は、読んだ本を登録してレビューを共有できる書籍レビューアプリです。ユーザーは書籍をジャンル付きで登録し、他のユーザーが投稿したレビューを閲覧・評価しながら、次に読む本を探せます。

Laravel 10 と MySQL 8.4 を Docker（Laravel Sail）上で動かし、画面は Blade + Tailwind CSS、認証は Laravel Fortify で実装しています。あわせて認証不要の公開 REST API を提供し、書籍データを外部から CRUD 操作できます。

### 実装した機能

| 機能 | 概要 |
| --- | --- |
| 認証 | Laravel Fortify による会員登録・ログイン・ログアウト（2要素認証は不使用） |
| 書籍 CRUD | 書籍の一覧（10件ページネーション・平均評価の星表示）・詳細・登録・編集・削除。編集と削除は登録者本人のみ（`BookPolicy`） |
| レビュー | 書籍へのレビュー投稿（1〜5の評価＋コメント）・編集・削除。同じ書籍へ複数回投稿可能。編集と削除は投稿者本人のみ（`ReviewPolicy`） |
| お気に入り | 書籍のお気に入り登録・解除をワンクリックで切り替え。お気に入り一覧を10件ページネーションで表示 |
| いいね | レビューへのいいねを切り替え。自分のレビューにもいいね可能 |
| ジャンル管理 | ジャンルの一覧（書籍数付き）・詳細（所属書籍を10件ページネーション）・登録・編集・削除。書籍が紐付くジャンルは削除不可 |
| ランキング | レビュー平均評価の高い書籍 TOP 10 を表示（レビュー0件の書籍は除外、同点時はレビュー件数の多い順） |
| 公開 API | 認証不要の REST API（`/api/v1/books`）で書籍のキーワード検索・ジャンル絞り込み・CRUD を提供 |

### 品質

- テスト 169 件（Unit 32 件 / Feature 137 件）、アサーション 550 件、すべて PASS
- テストカバレッジ **92.0%**（全コントローラー・FormRequest・Resource・Policy・モデルが 100%）
- `Laravel Pint`（PSR-12 準拠）でエラー 0 件

---

## ER図

```mermaid
erDiagram
    users ||--o{ books : "登録する"
    users ||--o{ reviews : "投稿する"
    users ||--o{ favorites : "お気に入り登録する"
    users ||--o{ review_likes : "いいねする"
    books ||--o{ book_genre : "ジャンルを持つ"
    genres ||--o{ book_genre : "書籍を持つ"
    books ||--o{ reviews : "レビューされる"
    books ||--o{ favorites : "お気に入りされる"
    reviews ||--o{ review_likes : "いいねされる"

    users {
        bigint_unsigned id PK
        varchar_255 name
        varchar_255 email UK
        timestamp email_verified_at "nullable"
        varchar_255 password
        varchar_100 remember_token "nullable"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }

    genres {
        bigint_unsigned id PK
        varchar_255 name UK
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }

    books {
        bigint_unsigned id PK
        bigint_unsigned user_id FK "ON DELETE CASCADE"
        varchar_255 title
        varchar_255 author
        varchar_13 isbn UK
        date published_date
        text description "nullable"
        varchar_2048 image_url "nullable"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }

    book_genre {
        bigint_unsigned book_id PK "FK to books / ON DELETE CASCADE"
        bigint_unsigned genre_id PK "FK to genres / ON DELETE RESTRICT"
    }

    reviews {
        bigint_unsigned id PK
        bigint_unsigned book_id FK "ON DELETE CASCADE"
        bigint_unsigned user_id FK "ON DELETE CASCADE"
        tinyint rating
        text comment
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }

    favorites {
        bigint_unsigned id PK
        bigint_unsigned user_id FK "ON DELETE CASCADE"
        bigint_unsigned book_id FK "ON DELETE CASCADE"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }

    review_likes {
        bigint_unsigned id PK
        bigint_unsigned user_id FK "ON DELETE CASCADE"
        bigint_unsigned review_id FK "ON DELETE CASCADE"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
```

### テーブル仕様の補足

| テーブル | 主キー / 制約 |
| --- | --- |
| `book_genre` | 複合主キー `(book_id, genre_id)`。`id` カラムと timestamps は持たない。`genre_id` のみ **ON DELETE RESTRICT**（書籍が紐付くジャンルを削除できないようにするため）、他の外部キーはすべて **ON DELETE CASCADE** |
| `reviews` | `(user_id, book_id)` に複合ユニーク制約は**付けない**（同一ユーザーが同じ書籍へ複数回レビューを投稿できる仕様） |
| `favorites` | `(user_id, book_id)` に複合ユニーク制約 |
| `review_likes` | `(user_id, review_id)` に複合ユニーク制約 |

---

## 環境構築手順

### 前提

Docker Desktop が起動していること。ホスト側のポート **80 / 3306 / 8080 / 5173** が空いていること。

### 1. リポジトリをクローン

```bash
git clone <このリポジトリのURL> bookshelf-app
cd bookshelf-app
```

### 2. 環境設定ファイルを作成

```bash
cp .env.example .env
```

`.env` は Sail 用の設定（`DB_HOST=mysql` / `DB_DATABASE=laravel` / `DB_USERNAME=sail` / `DB_PASSWORD=password`）が入った状態なので、そのまま使用できます。

### 3. PHP の依存パッケージをインストール

`vendor/` がまだ無い状態では `sail` コマンドが使えないため、公式イメージを使って `composer install` を実行します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

> このステップは依存パッケージの取得のみが目的で、`--ignore-platform-reqs` を付けているためイメージ側の PHP バージョンは問いません。アプリ本体は手順4で `vendor/laravel/sail/runtimes/8.5` からビルドされる **PHP 8.5** のコンテナで動作します。
>
> ローカルに PHP と Composer が入っている場合は、代わりに `composer install` を直接実行しても構いません。

### 4. Docker コンテナを起動

```bash
./vendor/bin/sail up -d
```

初回はイメージのビルドに数分かかります。`./vendor/bin/sail ps` で `laravel.test` / `mysql` / `phpmyadmin` の3コンテナが `Up` になるのを確認してください。

### 5. アプリケーションキーを生成

```bash
./vendor/bin/sail artisan key:generate
```

### 6. データベースの作成とシーディング

```bash
./vendor/bin/sail artisan migrate --seed
```

初期データとして ユーザー5件 / ジャンル10件 / 書籍11件 / レビュー32件 / お気に入り21件 / いいね50件 が投入されます。

> すでにテーブルがある状態でやり直す場合は `./vendor/bin/sail artisan migrate:fresh --seed` を使用してください。

### 7. フロントエンドのビルド

画面は Vite でビルドした CSS / JS を読み込むため、このステップは**必須**です（未実行だと画面表示時にエラーになります）。

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

開発中にホットリロードを使う場合は、ビルドの代わりに `./vendor/bin/sail npm run dev` を実行したままにしてください。

### 8. 動作確認

ブラウザで http://localhost を開くと書籍一覧が表示されます。

### テスト用ログインアカウント

シーディングで作成される5アカウントはすべてパスワードが `password` です。

| 名前 | メールアドレス | パスワード |
| --- | --- | --- |
| 山田太郎 | `yamada@example.com` | `password` |
| 鈴木花子 | `suzuki@example.com` | `password` |
| 田中一郎 | `tanaka@example.com` | `password` |
| 佐藤美咲 | `sato@example.com` | `password` |
| 高橋健太 | `takahashi@example.com` | `password` |

書籍11件はすべて **山田太郎**（`yamada@example.com`）が登録者です。書籍の編集・削除は登録者本人のみ可能なため、書籍 CRUD を確認する場合はこのアカウントでログインしてください。他アカウントでアクセスすると 403 になります（これは仕様どおりの挙動です）。

### テストの実行方法

テストは `phpunit.xml` の設定により MySQL 上の **`testing` データベース**を使用します。開発用の `laravel` データベースのシードデータは破壊されません。

```bash
# 全テストを実行（169件）
./vendor/bin/sail artisan test

# カバレッジ付きで実行
./vendor/bin/sail artisan test --coverage

# 特定のテストのみ実行
./vendor/bin/sail artisan test --filter=PublicApiTest
./vendor/bin/sail artisan test --testsuite=Unit
```

`testing` データベースは Sail の初期化スクリプトが自動作成します。万一存在しない場合は次のコマンドで作成してください。

```bash
./vendor/bin/sail mysql -e "CREATE DATABASE IF NOT EXISTS testing;"
```

### コードフォーマット

```bash
# 整形を実行
./vendor/bin/sail bin pint

# 差分チェックのみ（CI 用）
./vendor/bin/sail bin pint --test
```

---

## 使用技術

### バックエンド

| 技術 | バージョン |
| --- | --- |
| PHP | 8.5.8 |
| Laravel Framework | 10.50.4（`^10.10`） |
| MySQL | 8.4.11 |
| Laravel Fortify | `^1.36`（認証） |
| Laravel Sanctum | `^3.3` |
| Laravel Tinker | `^2.8` |

### フロントエンド

| 技術 | バージョン |
| --- | --- |
| Blade | Laravel 10 標準 |
| Tailwind CSS | `^3.4.19` |
| @tailwindcss/forms | `^0.5.11` |
| Alpine.js | `^3.17.5` |
| Vite | `^5.0.0` |
| laravel-vite-plugin | `^1.0.0` |
| Axios | `^1.6.4` |

### 開発環境・品質管理

| 技術 | バージョン |
| --- | --- |
| Docker / Laravel Sail | `^1.69` |
| phpMyAdmin | latest |
| PHPUnit | `^10.1` |
| Laravel Pint | `^1.0`（PSR-12） |

### 設計方針

- DB 操作は Eloquent ORM を使用し、N+1 問題対策として `with()` / `withCount()` / `withAvg()` を活用
- バリデーションは `FormRequest` に分離し、全クラスで `messages()` に日本語メッセージを定義
- 認可は `Policy` に分離し、コントローラーで `$this->authorize()` を呼び出し
- API のレスポンス整形は API Resource（`BookResource` / `GenreResource` / `ReviewResource`）に分離
- エラーメッセージは `lang/ja/` の言語ファイルで日本語化（`config/app.php` の `locale` は `ja`）

---

## 作成者

（ここに自分の名前を記入）

---

## APIエンドポイント一覧

公開 API は**認証不要**です。ベース URL は `http://localhost/api/v1` です。

| # | メソッド | パス | 概要 |
| --- | --- | --- | --- |
| AP01 | `GET` | `/api/v1/books` | 書籍一覧。キーワード検索・ジャンル絞り込み・ページネーションに対応 |
| AP02 | `GET` | `/api/v1/books/{book}` | 書籍詳細。ジャンルとレビュー（投稿者名付き）を含む |
| AP03 | `POST` | `/api/v1/books` | 書籍を登録（201 Created） |
| AP04 | `PUT` | `/api/v1/books/{book}` | 書籍を更新（200 OK） |
| AP05 | `DELETE` | `/api/v1/books/{book}` | 書籍を削除（204 No Content） |

### 共通仕様

- リクエストボディは JSON（`Content-Type: application/json`）とフォーム形式の両方に対応
- `Accept: application/json` ヘッダーが無い場合でも、`api/*` へのリクエストは常に JSON で応答します
- バリデーションエラーは **422 Unprocessable Content**、`message` と `errors` を含む JSON を返します
- 存在しない書籍 ID を指定した場合は **404 Not Found** で `{"error": "書籍が見つかりませんでした。"}` を返します
- 1分あたり60リクエストのレート制限（`throttle:api`）が適用されます

---

### AP01 `GET /api/v1/books`

書籍一覧を取得します。

**クエリパラメータ**

| パラメータ | 型 | 必須 | 説明 |
| --- | --- | --- | --- |
| `keyword` | string | 任意 | タイトルまたは著者の部分一致検索（最大255文字） |
| `genre_id` | integer | 任意 | 指定したジャンルを持つ書籍に絞り込み（`genres.id` に存在する値） |
| `page` | integer | 任意 | ページ番号（1以上）。既定値 1 |
| `per_page` | integer | 任意 | 1ページあたりの件数（1〜100）。既定値 20 |

**リクエスト例**

```bash
# 全件取得
curl "http://localhost/api/v1/books"

# 「夏目」でキーワード検索（タイトル・著者の部分一致）
curl -G "http://localhost/api/v1/books" --data-urlencode "keyword=夏目"

# ジャンルID 3（技術書）で絞り込み、1ページ5件
curl "http://localhost/api/v1/books?genre_id=3&per_page=5&page=1"
```

> キーワードに日本語を使う場合は `--data-urlencode` などで URL エンコードしてください。

**レスポンス例（200 OK）**

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "title": "吾輩は猫である",
      "author": "夏目漱石",
      "isbn": "9784101010014",
      "published_date": "1905-01-01",
      "description": "中学校の英語教師である苦沙弥先生の家に住む猫の視点から、明治の知識人たちの滑稽な日常を描いた長編小説。風刺とユーモアに満ちた文体が魅力です。",
      "image_url": "https://placehold.co/300x400?text=1",
      "genres": [
        { "id": 1, "name": "小説" }
      ],
      "average_rating": 4,
      "reviews_count": 4,
      "created_at": "2026-11-15T06:43:32.000000Z",
      "updated_at": "2026-11-15T06:43:32.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost/api/v1/books?page=1",
    "last": "http://localhost/api/v1/books?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 11
  }
}
```

- `average_rating` はレビュー平均評価（小数2桁に丸め）。レビューが0件の場合は `null`
- 一覧では `reviews` 配列は含まれません（詳細 API のみ）
- `meta` には上記のほか `from` / `to` / `path` / `links` も含まれます（Laravel 標準のページネーション形式）

**エラー例（422 Unprocessable Content）**

```json
{
  "message": "1ページあたりの件数は100以下で指定してください。",
  "errors": {
    "per_page": ["1ページあたりの件数は100以下で指定してください。"]
  }
}
```

---

### AP02 `GET /api/v1/books/{book}`

書籍詳細を取得します。ジャンルとレビュー（投稿者名・評価・コメント）を含みます。

**リクエスト例**

```bash
curl "http://localhost/api/v1/books/3"
```

**レスポンス例（200 OK）**

```json
{
  "data": {
    "id": 3,
    "user_id": 1,
    "title": "リーダブルコード",
    "author": "Dustin Boswell",
    "isbn": "9784873115658",
    "published_date": "2012-06-23",
    "description": "読みやすいコードを書くための具体的なテクニックを解説した一冊。命名、コメント、制御フローの改善など、実務で即座に使える知見が詰まっています。",
    "image_url": "https://placehold.co/300x400?text=3",
    "genres": [
      { "id": 3, "name": "技術書" }
    ],
    "average_rating": 4.5,
    "reviews_count": 4,
    "reviews": [
      {
        "id": 8,
        "user_name": "山田太郎",
        "rating": 5,
        "comment": "命名とコメントの章だけでも読む価値があります。チームの新人全員に配りたい一冊です。",
        "created_at": "2026-11-15T06:43:32.000000Z"
      }
    ],
    "created_at": "2026-11-15T06:43:32.000000Z",
    "updated_at": "2026-11-15T06:43:32.000000Z"
  }
}
```

**エラー例（404 Not Found）**

```json
{
  "error": "書籍が見つかりませんでした。"
}
```

---

### AP03 `POST /api/v1/books`

書籍を登録します。

**リクエストボディ**

| パラメータ | 型 | 必須 | 説明 |
| --- | --- | --- | --- |
| `user_id` | integer | **必須** | 登録者のユーザーID（`users.id` に存在する値） |
| `title` | string | **必須** | タイトル（最大255文字） |
| `author` | string | **必須** | 著者（最大255文字） |
| `isbn` | string | **必須** | 13桁の数字。`books.isbn` で一意 |
| `published_date` | date | **必須** | 出版日（例 `2026-11-15`） |
| `description` | string | 任意 | 説明 |
| `image_url` | string | 任意 | 画像URL（URL形式・最大2048文字） |
| `genres` | array | **必須** | ジャンルIDの配列（1つ以上、各要素は `genres.id` に存在する値） |

**リクエスト例**

```bash
curl -X POST "http://localhost/api/v1/books" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "title": "新しい書籍",
    "author": "著者名",
    "isbn": "9781234567897",
    "published_date": "2026-11-15",
    "description": "書籍の説明です。",
    "image_url": "https://placehold.co/300x400?text=new",
    "genres": [1, 3]
  }'
```

**レスポンス例（201 Created）**

```json
{
  "data": {
    "id": 12,
    "user_id": 1,
    "title": "新しい書籍",
    "author": "著者名",
    "isbn": "9781234567897",
    "published_date": "2026-11-15",
    "description": "書籍の説明です。",
    "image_url": "https://placehold.co/300x400?text=new",
    "genres": [
      { "id": 1, "name": "小説" },
      { "id": 3, "name": "技術書" }
    ],
    "average_rating": null,
    "reviews_count": 0,
    "created_at": "2026-11-15T07:10:00.000000Z",
    "updated_at": "2026-11-15T07:10:00.000000Z"
  }
}
```

**エラー例（422 Unprocessable Content）**

```json
{
  "message": "タイトルを入力してください。 (and 2 more errors)",
  "errors": {
    "title": ["タイトルを入力してください。"],
    "isbn": ["ISBNは13桁の数字で入力してください。"],
    "genres": ["ジャンルを1つ以上指定してください。"]
  }
}
```

---

### AP04 `PUT /api/v1/books/{book}`

書籍を更新します。リクエストボディは AP03 と同じで、`user_id` も必須です。`isbn` の一意性チェックは更新対象のレコード自身を除外するため、ISBN を変更せずに更新できます。

**リクエスト例**

```bash
curl -X PUT "http://localhost/api/v1/books/12" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 2,
    "title": "更新後のタイトル",
    "author": "更新後の著者",
    "isbn": "9781234567897",
    "published_date": "2026-11-16",
    "description": "更新後の説明です。",
    "image_url": "https://placehold.co/300x400?text=upd",
    "genres": [6]
  }'
```

**レスポンス例（200 OK）**

```json
{
  "data": {
    "id": 12,
    "user_id": 2,
    "title": "更新後のタイトル",
    "author": "更新後の著者",
    "isbn": "9781234567897",
    "published_date": "2026-11-16",
    "description": "更新後の説明です。",
    "image_url": "https://placehold.co/300x400?text=upd",
    "genres": [
      { "id": 6, "name": "歴史" }
    ],
    "average_rating": null,
    "reviews_count": 0,
    "created_at": "2026-11-15T07:10:00.000000Z",
    "updated_at": "2026-11-15T07:12:00.000000Z"
  }
}
```

ジャンルは `sync()` で同期されるため、リクエストに含まれないジャンルの紐付けは解除されます。

**エラー例（404 Not Found）**

```json
{
  "error": "書籍が見つかりませんでした。"
}
```

---

### AP05 `DELETE /api/v1/books/{book}`

書籍を削除します。外部キーの `ON DELETE CASCADE` により、紐付くジャンル（`book_genre`）・レビュー・お気に入り・レビューのいいねも同時に削除されます。

**リクエスト例**

```bash
curl -X DELETE "http://localhost/api/v1/books/12" -i
```

**レスポンス（204 No Content）**

レスポンスボディはありません。

```
HTTP/1.1 204 No Content
```

**エラー例（404 Not Found）**

```json
{
  "error": "書籍が見つかりませんでした。"
}
```

---

## 開発環境URL

| 用途 | URL | 備考 |
| --- | --- | --- |
| トップ / 書籍一覧 | http://localhost | `/` と `/books` の両方で書籍一覧を表示 |
| 公開 API（書籍一覧） | http://localhost/api/v1/books | 認証不要 |
| phpMyAdmin | http://localhost:8080 | `compose.yaml` の `FORWARD_PHPMYADMIN_PORT`（既定 `8080`）。ユーザー `sail` / パスワード `password` で自動ログイン |
| Vite 開発サーバー | http://localhost:5173 | `sail npm run dev` 実行時のみ |

### 画面一覧

| 画面 | URL | 認証 |
| --- | --- | --- |
| 書籍一覧 | http://localhost/books | 不要 |
| 書籍詳細 | http://localhost/books/1 | 不要 |
| 評価ランキング | http://localhost/ranking | 不要 |
| ログイン | http://localhost/login | 不要 |
| 会員登録 | http://localhost/register | 不要 |
| 書籍登録 | http://localhost/books/create | **必要** |
| 書籍編集 | http://localhost/books/1/edit | **必要**（登録者本人のみ） |
| お気に入り一覧 | http://localhost/favorites | **必要** |
| ジャンル管理 | http://localhost/genres | **必要** |
| ジャンル詳細 | http://localhost/genres/1 | **必要** |

### ポート変更

ホスト側のポートが使用中の場合は `.env` に以下を追記して `./vendor/bin/sail up -d` を再実行してください。

```dotenv
APP_PORT=8000
FORWARD_DB_PORT=33060
FORWARD_PHPMYADMIN_PORT=8081
VITE_PORT=5174
```
