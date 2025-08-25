<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Connection extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'platform',
    'access_token',
    'status',
  ];
  protected $hidden = [
    'user_id',
  ];

  protected $casts = [
    'status' => 'boolean',
  ];
  public function user()
  {
    return $this->belongsTo('App\Models\User');
  }
}
