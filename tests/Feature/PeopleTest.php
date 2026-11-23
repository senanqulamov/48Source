<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRule;
use App\Livewire\Suppliers\Index as Suppliers;
use App\Livewire\Users\Index as Users;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Services\SupplierLifecycleService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PeopleTest extends TestCase
{
    public function test_inviting_a_user_sends_a_set_password_link_and_never_a_password(): void
    {
        Notification::fake();
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(Users::class)->call('create')->set('f.name', 'Nora New')->set('f.email', 'Nora@Example.test')->set('f.role', 'buyer')->call('save')->assertHasNoErrors();
        $u = User::where('email', 'nora@example.test')->firstOrFail();
        $this->assertSame('invited', $u->status);
        $this->assertFalse(Hash::check('password', $u->password));
        Notification::assertSentTo($u, ResetPassword::class);
        $this->assertDatabaseHas('activity_logs', ['area' => 'Users', 'subject_id' => $u->id]);
    }

    public function test_a_supplier_user_must_belong_to_a_supplier_and_emails_are_unique(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(Users::class)->call('create')->set('f.name', 'Sam Sup')->set('f.email', 'sam@example.test')->set('f.role', 'supplier')->call('save')->assertHasErrors('f.supplier_id');
        Livewire::actingAs($admin)->test(Users::class)->call('create')->set('f.name', 'Dup')->set('f.email', $admin->email)->set('f.role', 'buyer')->call('save')->assertHasErrors('f.email');
    }

    public function test_an_admin_cannot_deactivate_themselves_or_the_last_admin(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(Users::class)->call('toggle', $admin->id);
        $this->assertSame('active', $admin->refresh()->status);
        $buyer = $this->buyer();
        Livewire::actingAs($admin)->test(Users::class)->call('toggle', $buyer->id);
        $this->assertSame('disabled', $buyer->refresh()->status);
        Livewire::actingAs($admin)->test(Users::class)->call('edit', $admin->id)->set('f.role', 'buyer')->call('save')->assertHasErrors('f.role');
        $this->assertSame('admin', $admin->refresh()->role);
    }

    public function test_supplier_lifecycle_follows_the_allowed_moves_and_records_reasons(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $su = User::factory()->supplier(Supplier::factory()->pending()->create())->create()->fresh();
        $s = $su->supplier;
        $life = app(SupplierLifecycleService::class);
        $this->actingAs($admin);
        foreach ([['inactive', 'x'], ['pending', null]] as [$to, $why]) {
            try {
                $life->change($s, $to, $admin, $why);
                $this->fail("Moved pending to {$to}");
            } catch (BusinessRule) {
                $this->addToAssertionCount(1);
            }
        }
        Livewire::actingAs($admin)->test(Suppliers::class)->call('askStatus', $s->id, 'active')->call('changeStatus');
        $this->assertSame('active', $s->refresh()->status);
        $this->assertSame($admin->id, $s->approved_by);
        Notification::assertSentTo($su, SystemNotice::class);
        Livewire::actingAs($admin)->test(Suppliers::class)->call('askStatus', $s->id, 'blocked')->set('reason', '')->call('changeStatus');
        $this->assertSame('active', $s->refresh()->status, 'Blocking needs a reason');
        Livewire::actingAs($admin)->test(Suppliers::class)->call('askStatus', $s->id, 'blocked')->set('reason', 'Falsified certificates')->call('changeStatus');
        $this->assertSame('blocked', $s->refresh()->status);
        $this->assertDatabaseHas('activity_logs', ['area' => 'Supplier', 'supplier_id' => $s->id]);
    }

    public function test_a_blocked_supplier_cannot_be_invited_or_quote(): void
    {
        $buyer = $this->buyer();
        $su = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$su]);
        $this->actingAs($this->admin());
        app(SupplierLifecycleService::class)->change($su->supplier, 'blocked', $this->admin(), 'Compliance hold');
        $this->expectException(BusinessRule::class);
        $this->quote($rfq, $su->fresh(), ['1', '1']);
    }
}
