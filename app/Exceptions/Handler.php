<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        /*
         * api/* ではモデル未検出と認可失敗を確定仕様のJSONで返す。
         *
         * ModelNotFoundException と AuthorizationException は
         * prepareException() により renderable のコールバックが呼ばれる前に
         * NotFoundHttpException / AccessDeniedHttpException へ変換されるため、
         * 変換後の例外を受け取る。モデル未検出はルートモデルバインディング
         * 失敗と区別できないため、元の例外を見て判定する。
         */
        $this->renderable(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            return $request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException
                ? $this->modelNotFoundResponse()
                : null;
        });

        // 認可失敗（AP06）。
        $this->renderable(function (AccessDeniedHttpException $e, Request $request): ?JsonResponse {
            return $request->is('api/*') ? $this->forbiddenResponse() : null;
        });
    }

    /**
     * api/* へのリクエストは Accept ヘッダーが無くても常にJSONで応答する。
     *
     * これによりバリデーションエラーが 422 のJSONで返る。
     * $request は親クラスが型宣言なしで定義しているため、PHPDocで型を示す
     * （引数の型を狭めるとシグネチャ非互換になる）。
     *
     * @param  Request  $request
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }

    /**
     * 認可失敗時の共通レスポンス。
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json(
            ['error' => 'この操作を実行する権限がありません。'],
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * モデル未検出時の共通レスポンス。
     */
    private function modelNotFoundResponse(): JsonResponse
    {
        return response()->json(
            ['error' => '書籍が見つかりませんでした。'],
            Response::HTTP_NOT_FOUND
        );
    }
}
