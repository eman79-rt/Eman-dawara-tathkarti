<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Media extends Model {protected $fillable=['path','collection','mediable_type','mediable_id']; public function mediable(){return $this->morphTo();}}
