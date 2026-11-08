<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ticket extends Model {protected $fillable=['booking_id','ticket_category_id','code','qr_payload','issued_at','checked_in_at']; protected function casts():array{return ['issued_at'=>'datetime','checked_in_at'=>'datetime'];} public function booking(){return $this->belongsTo(Booking::class);} public function category(){return $this->belongsTo(TicketCategory::class,'ticket_category_id');}}
