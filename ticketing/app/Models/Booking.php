<?php
namespace App\Models;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
class Booking extends Model {protected $fillable=['user_id','event_id','status','total','idempotency_key','expires_at','paid_at']; protected function casts():array{return ['status'=>BookingStatus::class,'expires_at'=>'datetime','paid_at'=>'datetime','total'=>'decimal:2'];} public function user(){return $this->belongsTo(User::class);} public function event(){return $this->belongsTo(Event::class);} public function items(){return $this->hasMany(BookingItem::class);} public function tickets(){return $this->hasMany(Ticket::class);} }
