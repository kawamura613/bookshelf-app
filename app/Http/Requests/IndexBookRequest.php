<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookRequest extends FormRequest
{
    /**
     * 書籍一覧は公開ページのため認可は不要。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 検索・フィルタ・ソートのクエリパラメータのルール。
     *
     * 提供済み Blade（books/index.blade.php）の name 属性
     * keyword / genre / sort に対応する。sort は Blade の
     * option の value（newest / oldest / rating / title）に加えて
     * 別名の latest も受け付ける。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'integer', 'exists:genres,id'],
            'sort' => ['nullable', 'string', 'in:latest,newest,oldest,rating,title'],
        ];
    }

    /**
     * 日本語バリデーションメッセージ。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードは文字列で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre.integer' => 'ジャンルの指定が正しくありません。',
            'genre.exists' => '指定されたジャンルは存在しません。',
            'sort.string' => '並び順の指定が正しくありません。',
            'sort.in' => '並び順の指定が正しくありません。',
        ];
    }
}
