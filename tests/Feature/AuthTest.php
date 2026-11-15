<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_会員登録画面が表示される(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_会員登録ができ_ログイン状態でホームへリダイレクトされる(): void
    {
        $response = $this->post('/register', [
            'name' => '新規太郎',
            'email' => 'shinki@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => '新規太郎',
            'email' => 'shinki@example.com',
        ]);
    }

    public function test_会員登録のパスワードはハッシュ化される(): void
    {
        $this->post('/register', [
            'name' => 'ハッシュ確認',
            'email' => 'hash@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'hash@example.com')->firstOrFail();

        $this->assertNotSame('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_会員登録のバリデーションエラーが日本語で返る(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください。',
            'email' => 'メールアドレスはメール形式で入力してください。',
        ]);
        $this->assertGuest();
    }

    public function test_会員登録でパスワードが8文字未満だとエラーになる(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'テスト',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['password' => 'パスワードは8文字以上で入力してください。']);
    }

    public function test_会員登録でパスワード確認が一致しないとエラーになる(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'テスト',
            'email' => 'test2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ])->assertSessionHasErrors(['password' => 'パスワードと確認用パスワードが一致しません。']);
    }

    public function test_会員登録でメールアドレスが重複するとエラーになる(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $this->from('/register')->post('/register', [
            'name' => 'テスト',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['email' => 'このメールアドレスは既に登録されています。']);

        $this->assertSame(1, User::where('email', 'duplicate@example.com')->count());
    }

    public function test_ログイン画面が表示される(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_正しい資格情報でログインできる(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);

        $response = $this->post('/login', [
            'email' => 'login@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_誤ったパスワードではログインできず日本語エラーが返る(): void
    {
        User::factory()->create(['email' => 'login2@example.com']);

        $response = $this->from('/login')->post('/login', [
            'email' => 'login2@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        $this->assertGuest();
    }

    public function test_未登録のメールアドレスではログインできない(): void
    {
        $this->from('/login')->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ログインの未入力バリデーションが日本語で返る(): void
    {
        $this->from('/login')->post('/login', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください。',
                'password' => 'パスワードを入力してください。',
            ]);
    }

    public function test_ログイン済みで_login_にアクセスするとホームへリダイレクトされる(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect('/');
    }

    public function test_ログイン済みで_register_にアクセスするとホームへリダイレクトされる(): void
    {
        $this->actingAs(User::factory()->create())->get('/register')->assertRedirect('/');
    }

    public function test_ログアウトするとログイン画面へリダイレクトされる(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
