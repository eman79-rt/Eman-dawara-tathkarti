<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BookingItem extends Model {protected $fillable=['booking_id','ticket_category_id','quantity','unit_price']; protected function casts():array{return ['unit_price'=>'decimal:2'];} public function booking(){return $this->belongsTo(Booking::class);} public function category(){return $this->belongsTo(TicketCategory::class,'ticket_category_id');}}
