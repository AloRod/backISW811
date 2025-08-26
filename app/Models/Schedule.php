<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
   * Usa la lógica mejorada del QueueScheduleService.
   */
  public function getNextOccurrence()
  {
    $fromDate = Carbon::now();
    Log::info('Now: ' . $fromDate);
    
    // Mapeo de días de la semana (1=lunes, 2=martes, etc.) a números de Carbon (0=domingo, 1=lunes, etc.)
    $carbonDayMap = [
      1 => 1, // Lunes
      2 => 2, // Martes
      3 => 3, // Miércoles
      4 => 4, // Jueves
      5 => 5, // Viernes
      6 => 6, // Sábado
      7 => 0, // Domingo
    ];
    
    $scheduleDayNumber = $carbonDayMap[$this->day_of_week];
    Log::info('scheduleDayNumber: ' . $scheduleDayNumber);
    
    // Crear la fecha/hora programada para esta semana
    $scheduledDateTime = $fromDate->copy()
      ->startOfWeek() // Ir al inicio de la semana (lunes)
      ->addDays($scheduleDayNumber === 0 ? 6 : $scheduleDayNumber - 1) // Ajustar al día correcto
      ->setTimeFromTimeString($this->time);
    
    
    // Si el horario ya pasó esta semana, mover a la próxima semana
    if ($scheduledDateTime->lte($fromDate)) {
      $scheduledDateTime->addWeek();
    }
    
    return $scheduledDateTime;
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
