<?php
namespace App\Models;
use App\Enums\TicketType;
use Illuminate\Database\Eloquent\Model;
class TicketCategory extends Model {protected $fillable=['event_id','name','type','price','quantity','sold','max_per_order']; protected function casts():array{return ['type'=>TicketType::class,'price'=>'decimal:2'];} public function event(){return $this->belongsTo(Event::class);} public function tickets(){return $this->hasMany(Ticket::class,'ticket_category_id');} public function getRemainingAttribute():int{return max(0,$this->quantity-$this->sold-(int)$this->activeReservations()->sum('quantity'));} public function activeReservations(){return $this->hasMany(BookingItem::class)->whereHas('booking',fn($q)=>$q->where('status','reserved')->where('expires_at','>',now()));}}
