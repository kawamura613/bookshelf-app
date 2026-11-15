# BookShelf 書籍レビューアプリ 開発ルール（フェーズ1：基本機能）

## 技術スタック・開発環境

- PHP 8.5 / Laravel 10.x / MySQL 8.4
- Docker環境（コマンドは `./vendor/bin/sail` を使用する）
- コード整形：`./vendor/bin/sail bin pint`（`sail bin pint --test` でエラー0必須）
- 認証：Laravel Fortify（2FAは不使用）

## コーディング規約（厳守）

- PSR-12準拠、型宣言（引数・戻り値、リレーションの HasMany/BelongsTo/BelongsToMany）を必ず記述する
- DB操作は Eloquent ORM を使用し、N+1問題対策で `with()`, `withCount()`, `withAvg()` を活用する
- バリデーションは必ず `FormRequest` に分離し、全クラスに `messages()` メソッドで日本語エラーメッセージを定義する
- 認可処理は `Policy` に分離し、コントローラーで `$this->authorize()` を使用する（GenrePolicyは不要）

## 確定DBスキーマ（フェーズ1：7テーブル）

1. `users`: id, name, email(unique), email_verified_at(nullable), password, remember_token, timestamps
2. `genres`: id, name(unique), timestamps
3. `books`: id, user_id(FK cascade), title, author, isbn(varchar 13, unique), published_date(date), description(text, nullable), image_url(varchar 2048, nullable), timestamps
4. `book_genre`: book_id(FK cascade), genre_id(FK restrict), primary(['book_id', 'genre_id'])
5. `reviews`: id, book_id(FK cascade), user_id(FK cascade), rating(tinyint), comment(text), timestamps（※user_idとbook_idのユニーク制約は付けない）
6. `favorites`: id, user_id(FK cascade), book_id(FK cascade), timestamps, unique(['user_id', 'book_id'])
7. `review_likes`: id, user_id(FK cascade), review_id(FK cascade), timestamps, unique(['user_id', 'review_id'])

## フラッシュメッセージ統一文言（キー：success / error）

- 書籍登録：`書籍を登録しました。`
- 書籍更新：`書籍情報を更新しました。`
- 書籍削除：`書籍を削除しました。`
- レビュー投稿：`レビューを投稿しました。`
- レビュー更新：`レビューを更新しました。`
- レビュー削除：`レビューを削除しました。`
- ジャンル登録：`ジャンルを作成しました。`
- ジャンル更新：`ジャンルを更新しました。`
- ジャンル削除：`ジャンルを削除しました。`
- ジャンル削除不可（error）：`このジャンルには書籍が紐付いているため削除できません。`
