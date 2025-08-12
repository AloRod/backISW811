<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class History extends Model
{
  protected $fillable = [
    'post_id',
    'status',
    'date',
    'time',
  ];

  public function post()
  {
    return $this->belongsTo('App\Models\Post');
  }
}
