<?php
namespace App\Listeners;
use App\Events\PurchaseConfirmed; use App\Jobs\IssueTicketsJob; use Illuminate\Contracts\Queue\ShouldQueue;
class QueuePurchaseFulfillment implements ShouldQueue { public string $queue='critical'; public int $tries=3; public array $backoff=[5,20,60]; public function handle(PurchaseConfirmed $event):void { IssueTicketsJob::dispatch($event->booking->id)->onQueue('critical'); } }
