<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends FormRequest
{
    /**
     * 認可はルートの auth ミドルウェアで担保する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール。
     *
     * 提供済み Blade（reading-plans/create.blade.php）の name 属性
     * book_id / target_date に対応する。
     * 同一ユーザーが同じ書籍で進行中の計画を重複して持てないようにする。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
                Rule::unique('reading_plans', 'book_id')->where(
                    fn (Builder $query): Builder => $query
                        ->where('user_id', Auth::id())
                        ->where('status', ReadingPlanStatus::InProgress->value)
                ),
            ],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
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
            'book_id.required' => '書籍を選択してください。',
            'book_id.integer' => '書籍の選択が正しくありません。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'book_id.unique' => 'この書籍は既に進行中の読書計画が存在します。',
            'target_date.required' => '期日を入力してください。',
            'target_date.date' => '期日は有効な日付を入力してください。',
            'target_date.after_or_equal' => '期日は本日以降の日付を入力してください。',
        ];
    }
}
