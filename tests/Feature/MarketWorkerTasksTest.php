<?php

use App\Livewire\MarketWork\Today;
use App\Livewire\Seller\Tasks\Index as SellerTasksIndex;
use App\Models\Market;
use App\Models\MarketWorkerTask;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    foreach (['seller', 'market_worker'] as $name) {
        Role::query()->firstOrCreate(['name' => $name], [
            'display_name' => ucfirst(str_replace('_', ' ', $name)),
            'is_system' => true,
        ]);
    }

    $this->seller = User::factory()->create(['role' => 'seller']);
    $this->worker = User::factory()->create([
        'role' => 'market_worker',
        'seller_id' => $this->seller->id,
    ]);
    $this->market = Market::factory()->create(['user_id' => $this->seller->id]);
    $this->worker->workerMarkets()->syncWithoutDetaching([$this->market->id]);
});

it('creates a real market task for a worker assigned to the selected market', function () {
    $this->actingAs($this->seller);

    Livewire::test(SellerTasksIndex::class)
        ->set('marketId', $this->market->id)
        ->set('workerId', $this->worker->id)
        ->set('title', 'Check availability for listed products')
        ->set('type', 'availability')
        ->set('details', 'Verify product availability')
        ->call('createTask')
        ->assertHasNoErrors()
        ->assertDispatched('task-created');

    $task = MarketWorkerTask::query()->firstOrFail();
    expect($task->worker_id)->toBe($this->worker->id)
        ->and($task->seller_id)->toBe($this->seller->id)
        ->and($task->market_id)->toBe($this->market->id)
        ->and($task->completed_at)->toBeNull();
});

it('lets only its assigned worker toggle actual completion', function () {
    $task = MarketWorkerTask::create([
        'seller_id' => $this->seller->id,
        'worker_id' => $this->worker->id,
        'market_id' => $this->market->id,
        'title' => 'Inspect today orders',
        'type' => 'order',
    ]);

    $this->actingAs($this->worker);
    Livewire::test(Today::class)->call('toggle', $task->id)->assertHasNoErrors();
    expect($task->fresh()->completed_at)->not->toBeNull();

    Livewire::test(Today::class)->call('toggle', $task->id)->assertHasNoErrors();
    expect($task->fresh()->completed_at)->toBeNull();
});

it('rejects assigning a task to a worker outside the selected market', function () {
    $otherMarket = Market::factory()->create(['user_id' => $this->seller->id]);
    $this->actingAs($this->seller);

    Livewire::test(SellerTasksIndex::class)
        ->set('marketId', $otherMarket->id)
        ->set('workerId', $this->worker->id)
        ->set('title', 'Check order dispatch')
        ->set('type', 'order')
        ->call('createTask')
        ->assertHasErrors(['workerId']);

    expect(MarketWorkerTask::count())->toBe(0);
});
