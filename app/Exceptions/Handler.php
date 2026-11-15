<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        // api/* でモデルが見つからない場合は確定仕様のJSONを返す。
        $this->renderable(function (ModelNotFoundException $e, Request $request): ?JsonResponse {
            return $request->is('api/*') ? $this->modelNotFoundResponse() : null;
        });

        // ルートモデルバインディング失敗時は ModelNotFoundException が
        // NotFoundHttpException に変換されるため、元の例外を見て判定する。
        $this->renderable(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            return $request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException
                ? $this->modelNotFoundResponse()
                : null;
        });
    }

    /**
     * api/* へのリクエストは Accept ヘッダーが無くても常にJSONで応答する。
     *
     * これによりバリデーションエラーが 422 のJSONで返る。
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
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
