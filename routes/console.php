<?php

use App\Services\RfqService;
use Illuminate\Support\Facades\Schedule;

// Requests stop accepting quotations at their deadline even if nobody is signed in.
Schedule::call(fn () => app(RfqService::class)->closeExpired())->everyFiveMinutes()->name('rfqs:close-expired')->withoutOverlapping();

Schedule::call(fn () => app(\App\Services\AuctionService::class)->endExpired())->everyMinute()->name('auctions:end-expired')->withoutOverlapping();
Schedule::call(fn () => app(\App\Services\BafoService::class)->closeExpired())->everyFiveMinutes()->name('bafo:close-expired')->withoutOverlapping();
Schedule::call(fn () => app(\App\Services\RfiService::class)->closeExpired())->everyFiveMinutes()->name('rfis:close-expired')->withoutOverlapping();
Schedule::call(fn () => app(\App\Services\ContractService::class)->expireEnded())->dailyAt('00:10')->name('contracts:expire')->withoutOverlapping();
Schedule::call(fn () => app(\App\Services\ReminderService::class)->run())->hourly()->name('reminders:run')->withoutOverlapping();
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('scheduler:heartbeat', now()->toIso8601String(), 86400))->everyMinute()->name('scheduler:heartbeat');
