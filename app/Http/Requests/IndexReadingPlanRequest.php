<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReadingPlanRequest extends FormRequest
{
    /**
     * 認可はルートの auth ミドルウェアで担保する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 絞り込みのクエリパラメータのルール。
     *
     * 提供済み Blade（reading-plans/index.blade.php）の
     * name="status" に対応し、ReadingPlanStatus の値のみ許可する。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in(array_column(ReadingPlanStatus::cases(), 'value')),
            ],
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
            'status.string' => '状態の指定が正しくありません。',
            'status.in' => '状態の指定が正しくありません。',
        ];
    }
}
