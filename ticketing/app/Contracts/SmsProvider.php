<?php namespace App\Contracts; interface SmsProvider {public function send(string $to,string $message,string $dedupeKey):void;}
