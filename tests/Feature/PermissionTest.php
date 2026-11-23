<?php

namespace Tests\Feature;

use App\Livewire\Categories\Index as Categories;
use App\Livewire\Orders\Show as OrderShow;
use App\Livewire\Quotes\Invitations;
use App\Livewire\Rfqs\Compare;
use App\Livewire\Rfqs\Form;
use App\Livewire\Rfqs\Show;
use App\Livewire\Suppliers\Index as Suppliers;
use App\Livewire\Users\Index as Users;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Supplier;
use App\Services\AwardService;
use App\Support\Nav;
use App\Support\Perm;
use Livewire\Livewire;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    public function test_defaults_give_each_role_its_own_areas(): void
    {
        $buyer = $this->buyer();
        $su = $this->supplierUser();
        $this->assertTrue($buyer->allowed('rfqs', 'C'));
        $this->assertFalse($buyer->allowed('users'));
        $this->assertTrue($su->allowed('quotes', 'C'));
        $this->assertFalse($su->allowed('rfqs', 'C'));
        $this->assertFalse($su->allowed('suppliers'));
        $this->assertTrue($this->admin()->allowed('system', 'A'));
    }

    public function test_removing_view_closes_the_page_and_hides_the_menu_item(): void
    {
        $buyer = $this->buyer();
        foreach (['rfqs' => 'rfqs.index', 'orders' => 'orders.index', 'suppliers' => 'suppliers.index', 'quotes' => 'compare.index', 'catalog' => 'categories.index'] as $module => $route) {
            $this->actingAs($buyer)->get(route($route))->assertOk();
            $this->assertContains($route, collect(Nav::for($buyer))->flatMap(fn ($g) => collect($g[1])->pluck('route'))->all());
            Perm::set('buyer', $module, '');
            $this->actingAs($buyer)->get(route($route))->assertForbidden();
            $this->assertNotContains($route, collect(Nav::for($buyer))->flatMap(fn ($g) => collect($g[1])->pluck('route'))->all());
            Perm::set('buyer', $module, Perm::DEFAULTS['buyer'][$module]);
        }
    }

    public function test_without_create_a_buyer_cannot_start_a_request(): void
    {
        $buyer = $this->buyer();
        Perm::set('buyer', 'rfqs', 'VE');
        $this->actingAs($buyer)->get(route('rfqs.create'))->assertForbidden();
        $this->actingAs($buyer)->get(route('rfqs.index'))->assertOk()->assertDontSee('New RFQ');
        Livewire::actingAs($buyer)->test(Form::class)->set('f.title', 'Sneaky request')->call('saveDraft')->assertForbidden();
        $this->assertSame(0, Rfq::count());
    }

    public function test_without_edit_a_buyer_cannot_close_cancel_or_issue(): void
    {
        $buyer = $this->buyer();
        $su = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$su]);
        Perm::set('buyer', 'rfqs', 'VC');
        Livewire::actingAs($buyer)->test(Show::class, ['number' => $rfq->number])->assertDontSee('Close quoting')->call('closeQuoting')->assertForbidden();
        Livewire::actingAs($buyer)->test(Show::class, ['number' => $rfq->number])->set('reason', 'A good enough reason')->call('cancel')->assertForbidden();
        $this->assertSame('open', $rfq->refresh()->status);

        Perm::set('buyer', 'rfqs', 'VCE');
        $q = $this->quote($rfq, $su, ['1', '1']);
        $po = app(AwardService::class)->award($this->closeRfq($rfq), $q, $buyer);
        Perm::set('buyer', 'orders', 'V');
        Livewire::actingAs($buyer)->test(OrderShow::class, ['number' => $po->number])->assertDontSee('Issue to supplier')->call('advance')->assertForbidden();
        $this->assertSame('draft', $po->refresh()->status);
    }

    public function test_without_approve_a_buyer_cannot_award(): void
    {
        $buyer = $this->buyer();
        $su = $this->supplierUser();
        $rfq = $this->openRfq($buyer, [$su]);
        $q = $this->quote($rfq, $su, ['1', '1']);
        $rfq = $this->closeRfq($rfq);
        Perm::set('buyer', 'quotes', 'V');
        Livewire::actingAs($buyer)->test(Compare::class, ['number' => $rfq->number])->assertDontSee('Award and create')->call('choose', $q->id)->assertForbidden();
        Livewire::actingAs($buyer)->test(Compare::class, ['number' => $rfq->number])->set('pick', $q->id)->call('award')->assertForbidden();
        Livewire::actingAs($buyer)->test(Compare::class, ['number' => $rfq->number])->call('splitAward')->assertForbidden();
        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_supplier_permissions_gate_responding_and_quoting(): void
    {
        $su = $this->supplierUser();
        $rfq = $this->openRfq($this->buyer(), [$su]);
        Perm::set('supplier', 'quotes', 'V');
        Livewire::actingAs($su)->test(Invitations::class)->assertSee($rfq->number)->assertDontSee("accept('", false)->call('accept', $rfq->number)->assertForbidden();
        Livewire::actingAs($su)->test(\App\Livewire\Quotes\Form::class, ['number' => $rfq->number])->set('delivery_days', '5')->call('submit')->assertForbidden();
        $this->assertSame(0, $rfq->quotes()->count());
    }

    public function test_supplier_management_abilities_are_separate(): void
    {
        $buyer = $this->buyer(); // suppliers: VE by default
        $s = Supplier::factory()->pending()->create();
        Livewire::actingAs($buyer)->test(Suppliers::class)->call('create')->assertForbidden();
        Livewire::actingAs($buyer)->test(Suppliers::class)->call('askStatus', $s->id, 'active')->assertForbidden();
        Livewire::actingAs($buyer)->test(Suppliers::class)->set('openId', $s->id)->set('to', 'active')->call('changeStatus')->assertForbidden();
        $this->assertSame('pending', $s->refresh()->status);
        Livewire::actingAs($buyer)->test(Suppliers::class)->call('edit', $s->id)->set('f.city', 'Baku')->call('save')->assertHasNoErrors();
        $this->assertSame('Baku', $s->refresh()->city);
        Livewire::actingAs($buyer)->test(Categories::class)->call('create')->assertForbidden();
    }

    public function test_only_someone_with_approve_on_users_can_change_the_permission_table(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(Users::class)->set('role', 'buyer')->call('togglePerm', 'rfqs', 'C');
        Perm::flush();
        $this->assertSame('VE', Perm::matrix()['buyer']['rfqs']);
        // removing View clears the rest; adding any ability brings View back
        Livewire::actingAs($admin)->test(Users::class)->set('role', 'buyer')->call('togglePerm', 'rfqs', 'V');
        $this->assertSame('', Perm::matrix()['buyer']['rfqs']);
        Livewire::actingAs($admin)->test(Users::class)->set('role', 'buyer')->call('togglePerm', 'rfqs', 'E');
        $this->assertSame('VE', Perm::matrix()['buyer']['rfqs']);
        // administrators cannot lock themselves out
        Livewire::actingAs($admin)->test(Users::class)->set('role', 'admin')->call('togglePerm', 'users', 'V');
        $this->assertSame('VCEA', Perm::matrix()['admin']['users']);

        Perm::set('admin', 'users', 'VCE');
        Livewire::actingAs($admin)->test(Users::class)->set('role', 'buyer')->call('togglePerm', 'orders', 'V')->assertForbidden();
        $this->actingAs($this->buyer())->get(route('users.index'))->assertForbidden();
    }
}
