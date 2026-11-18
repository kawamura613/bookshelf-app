<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ISBN・出版日が不明な書籍も登録できるよう NULL を許容する。
     * doctrine/dbal を追加せずにカラム定義を変更するため、
     * MySQL の ALTER TABLE ... MODIFY を直接発行する（unique 制約は保持される）。
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `books` MODIFY `isbn` VARCHAR(13) NULL');
        DB::statement('ALTER TABLE `books` MODIFY `published_date` DATE NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `books` MODIFY `isbn` VARCHAR(13) NOT NULL');
        DB::statement('ALTER TABLE `books` MODIFY `published_date` DATE NOT NULL');
    }
};
