<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

/** Every page renders for the roles that may open it, with realistic data. */
class PagesTest extends TestCase
{
    public function test_every_page_renders_for_each_role(): void
    {
        putenv('DEMO_PASSWORD=demo-password-for-tests-1');
        $this->seed(DemoSeeder::class);
        auth()->logout();
        $u = fn (string $email) => User::where('email', $email)->firstOrFail();
        $open = Rfq::where('status', 'open')->first();
        $closed = Rfq::where('status', 'closed')->first();
        $draft = Rfq::where('status', 'draft')->first();
        $po = PurchaseOrder::first();

        $sourcing = ['/', '/rfqs', '/rfqs?status=open&q=steel', '/rfqs/create', "/rfqs/{$open->number}", "/rfqs/{$open->number}?tab=quotes", "/rfqs/{$open->number}?tab=suppliers", "/rfqs/{$open->number}?tab=items", "/rfqs/{$open->number}?tab=activity", "/rfqs/{$draft->number}/edit", "/rfqs/{$closed->number}/compare", '/compare', '/orders', "/orders/{$po->number}", '/suppliers', '/suppliers?status=pending', '/categories', '/notifications', '/settings', '/search?q=steel'];
        foreach (['admin@demo.48source.test' => array_merge($sourcing, ['/users', '/users?tab=roles', '/logs']), 'buyer@demo.48source.test' => $sourcing] as $email => $pages) {
            foreach ($pages as $page) {
                $this->actingAs($u($email))->get($page)->assertOk();
            }
        }
        foreach (['/', '/quotes', '/quotes?tab=sent', '/quotes?tab=closed', "/quotes/{$open->number}", "/quotes/{$closed->number}", '/orders', "/orders/{$po->number}", '/notifications', '/settings', '/search?q=rebar'] as $page) {
            $this->actingAs($u('supplier@demo.48source.test'))->get($page)->assertOk();
        }
        foreach (['supplier5@demo.48source.test', 'seller@demo.48source.test', 'worker@demo.48source.test', 'fieldworker@demo.48source.test'] as $email) {
            foreach (['/', '/notifications', '/settings'] as $page) {
                $this->actingAs($u($email))->get($page)->assertOk();
            }
        }
        // the pending supplier sees why it cannot act yet
        $this->actingAs($u('supplier5@demo.48source.test'))->get('/')->assertSee('waiting for approval');
        // buyer two is isolated from buyer one
        $this->actingAs($u('buyer2@demo.48source.test'))->get("/rfqs/{$open->number}")->assertNotFound();
    }
}
