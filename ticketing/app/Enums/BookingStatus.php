<?php
namespace App\Enums;
enum BookingStatus:string { case Reserved='reserved'; case Paid='paid'; case Refunded='refunded'; case Expired='expired'; }
