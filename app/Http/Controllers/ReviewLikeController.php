<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ReviewLikeController extends Controller
{
    /**
     * レビューへのいいねを切り替える。
     *
     * 自分自身のレビューへのいいねも許可する。
     * 処理後は元の画面へ戻す。
     */
    public function toggle(Review $review): RedirectResponse
    {
        Auth::user()->likedReviews()->toggle($review->id);

        return back();
    }
}
