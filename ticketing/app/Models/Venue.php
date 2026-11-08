<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Venue extends Model {protected $fillable=['name','city','address','capacity']; public function events(){return $this->hasMany(Event::class);} public function media(){return $this->morphMany(Media::class,'mediable');}}
