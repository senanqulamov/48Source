<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetNotice;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_guests_are_sent_to_the_sign_in_page(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/rfqs')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_a_user_signs_in_with_the_right_password_only(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-42']);
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong-password-1')->call('login')->assertHasErrors('email');
        $this->assertGuest();
        Livewire::test(Login::class)->set('email', strtoupper($user->email))->set('password', 'correct-horse-42')->call('login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'Signed in']);
    }

    public function test_deactivated_and_invited_accounts_cannot_sign_in(): void
    {
        $off = User::factory()->create(['password' => 'correct-horse-42', 'status' => 'disabled']);
        Livewire::test(Login::class)->set('email', $off->email)->set('password', 'correct-horse-42')->call('login')->assertHasErrors('email');
        $inv = User::factory()->create(['password' => 'correct-horse-42', 'status' => 'invited']);
        Livewire::test(Login::class)->set('email', $inv->email)->set('password', 'correct-horse-42')->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_sign_in_is_throttled_after_five_wrong_attempts(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-42']);
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->set('email', $user->email)->set('password', 'nope-nope-1')->call('login');
        }
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'correct-horse-42')->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_account_deactivated_mid_session_is_signed_out(): void
    {
        $user = $this->buyer();
        $this->actingAs($user)->get('/')->assertOk();
        $user->update(['status' => 'disabled']);
        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_supplier_registration_creates_a_pending_company_and_asks_for_email_confirmation(): void
    {
        Notification::fake();
        $admin = $this->admin();
        Livewire::test(Register::class)->set('company', 'Testco Metals')->set('name', 'Tess Tester')->set('email', 'Tess@Testco.example')
            ->set('password', 'long-password-77')->set('password_confirmation', 'long-password-77')->call('register')->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'tess@testco.example')->firstOrFail();
        $this->assertSame('supplier', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('pending', Supplier::findOrFail($user->supplier_id)->status);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($admin, \App\Notifications\SystemNotice::class);
        // until the email is confirmed the workspace stays closed
        $this->actingAs($user)->get('/')->assertRedirect(route('verification.notice'));
    }

    public function test_registration_rejects_weak_passwords_and_duplicate_emails(): void
    {
        $existing = User::factory()->create();
        Livewire::test(Register::class)->set('company', 'X Co')->set('name', 'Xa Xu')->set('email', $existing->email)->set('password', 'short')->set('password_confirmation', 'short')
            ->call('register')->assertHasErrors(['email', 'password']);
        $this->assertSame(0, Supplier::count());
    }

    public function test_password_reset_gives_the_same_answer_for_unknown_addresses(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Livewire::test(ForgotPassword::class)->set('email', 'nobody@nowhere.example')->call('send')->assertSet('sent', true);
        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('send')->assertSet('sent', true);
        Notification::assertSentTo($user, ResetNotice::class);
        Notification::assertCount(1);
    }

    public function test_an_invited_user_becomes_active_by_setting_a_password(): void
    {
        $user = User::factory()->unverified()->create(['status' => 'invited']);
        $token = Password::createToken($user);
        Livewire::withQueryParams(['email' => $user->email])->test(ResetPassword::class, ['token' => $token])
            ->set('password', 'brand-new-pass-9')->set('password_confirmation', 'brand-new-pass-9')->call('save')->assertRedirect(route('login'));
        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        Livewire::test(ResetPassword::class, ['token' => 'bad-token'])->set('email', $user->email)->set('password', 'another-pass-10')->set('password_confirmation', 'another-pass-10')->call('save')->assertHasErrors('email');
    }
}
