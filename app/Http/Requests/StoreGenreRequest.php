<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGenreRequest extends FormRequest
{
    /**
     * 認可はルートの auth ミドルウェアで担保する。
     * 確定仕様により、ログインユーザーであれば誰でも操作可能。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール。
     *
     * 提供済み Blade（genres/create.blade.php）の name 属性に対応する。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:genres,name'],
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
            'name.required' => 'ジャンル名を入力してください。',
            'name.string' => 'ジャンル名は文字列で入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'このジャンル名は既に登録されています。',
        ];
    }
}
