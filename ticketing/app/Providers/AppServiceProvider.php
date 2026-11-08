<?php
namespace App\Providers;
use App\Contracts\SmsProvider; use App\Services\LogSmsProvider; use App\Services\NullSmsProvider; use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {public function register():void {$this->app->bind(SmsProvider::class,fn()=>config('services.sms.driver')==='http'?new NullSmsProvider:new LogSmsProvider);} public function boot():void { \Laravel\Horizon\Horizon::auth(fn($request)=>app()->environment('local')||$request->user()?->role?->value==='admin'); }}
