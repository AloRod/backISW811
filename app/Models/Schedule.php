<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Schedule extends Model
{
  use HasFactory;

  protected $fillable = [
    'day_of_week',
    'time',
    'user_id',
  ];

  protected $hidden = [
    'user_id',
  ];

  const MONDAY = 1;
  const TUESDAY = 2;
  const WEDNESDAY = 3;
  const THURSDAY = 4;
  const FRIDAY = 5;
  const SATURDAY = 6;
  const SUNDAY = 7;

  public static function getDayNames()
  {
    return [
      self::MONDAY => 'Lunes',
      self::TUESDAY => 'Martes', 
      self::WEDNESDAY => 'Miércoles',
      self::THURSDAY => 'Jueves',
      self::FRIDAY => 'Viernes',
      self::SATURDAY => 'Sábado',
      self::SUNDAY => 'Domingo'
    ];
  }

  public static function getDayAbbreviations()
  {
    return [
      self::MONDAY => 'L',
      self::TUESDAY => 'K', 
      self::WEDNESDAY => 'M',
      self::THURSDAY => 'J',
      self::FRIDAY => 'V',
      self::SATURDAY => 'S',
      self::SUNDAY => 'D'
    ];
  }

  public function getDayNameAttribute()
  {
    return self::getDayNames()[$this->day_of_week] ?? '';
  }

  public function getDayAbbreviationAttribute()
  {
    return self::getDayAbbreviations()[$this->day_of_week] ?? '';
  }

  public function user()
  {
    return $this->belongsTo('App\Models\User');
  }

  /**
   * Obtener el próximo post de este horario a partir de una fecha determinada.
   */
  public function getNextOccurrence($fromDate = null)
  {
    $fromDate = $fromDate ? Carbon::parse($fromDate) : Carbon::now();
    $currentDayOfWeek = $fromDate->dayOfWeek;
    
    $currentDayOfWeek = $currentDayOfWeek === 0 ? 7 : $currentDayOfWeek;
    
    $daysUntilNext = $this->day_of_week - $currentDayOfWeek;
    
    // Si es el mismo día, verificar si el horario ya pasó
    if ($daysUntilNext === 0) {
      $time = Carbon::parse($this->time);
      $todayAtScheduleTime = $fromDate->copy()->setTime($time->hour, $time->minute, $time->second);
      
      // Si el horario de hoy ya pasó, ir al próximo día de la semana
      if ($todayAtScheduleTime->lte($fromDate)) {
        $daysUntilNext = 7;
      }
    } else if ($daysUntilNext < 0) {
      // Si el día ya pasó esta semana, ir a la próxima semana
      $daysUntilNext += 7;
    }
    
    $nextDate = $fromDate->copy()->addDays($daysUntilNext);
    $time = Carbon::parse($this->time);
    
    return $nextDate->setTime($time->hour, $time->minute, $time->second);
  }

  /**
   * Obtener el horario más cercano a partir de una fecha determinada.
   */
  public static function getClosestSchedule($userId, $fromDate = null)
  {
    $fromDate = $fromDate ? Carbon::parse($fromDate) : Carbon::now();
    $schedules = self::where('user_id', $userId)
      ->orderBy('day_of_week')
      ->orderBy('time')
      ->get();

    if ($schedules->isEmpty()) {
      return null;
    }

    $closestSchedule = null;
    $closestDateTime = null;

    foreach ($schedules as $schedule) {
      $nextOccurrence = $schedule->getNextOccurrence($fromDate);
      
      if (!$closestDateTime || $nextOccurrence->lt($closestDateTime)) {
        $closestDateTime = $nextOccurrence;
        $closestSchedule = $schedule;
      }
    }

    return $closestSchedule;
  }
}
