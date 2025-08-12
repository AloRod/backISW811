<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
  
 use HasFactory;
  protected $fillable = [
    'user_id',
    'post_text',
    'social_network',
  ];

  public function user()
  {
    return $this->belongsTo('App\Models\User');
  }

  //Funcion para que el model "post" tenga varios registros en el historial 
  public function histories()
  {
    return $this->hasMany('App\Models\History');
  }
}

