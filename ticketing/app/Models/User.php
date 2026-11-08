<?php
namespace App\Models;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable { use HasApiTokens, HasFactory, Notifiable; protected $fillable=['name','email','phone','password','role']; protected $hidden=['password','remember_token']; protected function casts():array{return ['email_verified_at'=>'datetime','password'=>'hashed','role'=>UserRole::class];} public function events(){return $this->hasMany(Event::class,'organizer_id');} public function bookings(){return $this->hasMany(Booking::class);} }
