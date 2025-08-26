<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\QueueScheduleService;
class ScheduleController extends Controller
{
  private $queueScheduleService;
  
  public function __construct(QueueScheduleService $queueScheduleService)
  {
    $this->queueScheduleService = $queueScheduleService;
  }
  public function index()
  {
    $schedules = Schedule::all();
    return response()->json(['data' => $schedules], 200);
  }

  public function show($id)
  {
    $schedule = Schedule::find($id);

    if (!$schedule) {
      return response()->json(['message' => 'Schedule not found'], 404);
    }

    return response()->json(['data' => $schedule], 200);
  }

  public function store(Request $request)
  {
    $data = $request->only('day_of_week', 'time', 'user_id');

    $validator = Validator::make($data, [
      'day_of_week' => 'required|integer|between:1,7',
      'time' => 'required|date_format:H:i',
      'user_id' => 'required|integer|exists:users,id',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $schedule = Schedule::create($data);

    return response()->json(['data' => $schedule], 201);
  }

  public function update(Request $request, $id)
  {
    $data = $request->only('day_of_week', 'time');

    $validator = Validator::make($data, [
      'day_of_week' => 'required|integer|between:1,7',
      'time' => 'required|date_format:H:i',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $schedule = Schedule::find($id);

    if (!$schedule) {
      return response()->json(['message' => 'Schedule not found'], 404);
    }

    $schedule->update($data);

    return response()->json(['data' => $schedule], 200);
  }

  public function destroy($id)
  {
    $schedule = Schedule::find($id);

    if (!$schedule) {
      return response()->json(['message' => 'Schedule not found'], 404);
    }

    $schedule->delete();

    return response()->json(null, 204);
  }

  /**
   * Filtrar por el usuario 
   */
  public function getByUserId($user_id)
  {
    $validator = Validator::make(['user_id' => $user_id], [
      'user_id' => 'required|integer|exists:users,id',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $schedules = Schedule::where('user_id', $user_id)
      ->orderBy('day_of_week')
      ->orderBy('time')
      ->get()
      ->map(function ($schedule) {
        $schedule->day_name = $schedule->day_name;
        $schedule->day_abbreviation = $schedule->day_abbreviation;
        $schedule->time_formatted = Carbon::parse($schedule->time)->format('H:i');
        $schedule->time_12h = Carbon::parse($schedule->time)->format('h:i A');
        return $schedule;
      });

    return response()->json(['data' => $schedules], 200);
  }

  /**
   * Get schedules by user ID and day of week
   */
  public function getByUserIdAndDay($user_id, $day_of_week)
  {
    $validator = Validator::make(['user_id' => $user_id, 'day_of_week' => $day_of_week], [
      'user_id' => 'required|integer|exists:users,id',
      'day_of_week' => 'required|integer|between:1,7',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $schedules = Schedule::where('user_id', $user_id)
      ->where('day_of_week', $day_of_week)
      ->orderBy('time')
      ->get()
      ->map(function ($schedule) use ($day_of_week) {
        $schedule->day_name = $schedule->day_name;
        $schedule->day_abbreviation = $schedule->day_abbreviation;
        $schedule->time_formatted = Carbon::parse($schedule->time)->format('H:i');
        $schedule->time_12h = Carbon::parse($schedule->time)->format('h:i A');
        $schedule->day_of_week = $day_of_week;
        return $schedule;
      });

    return response()->json(['data' => $schedules], 200);
  }

  /**
   * Get weekly schedule for a user (by days)
   */
  public function getWeeklySchedule($user_id)
  {
    $validator = Validator::make(['user_id' => $user_id], [
      'user_id' => 'required|integer|exists:users,id',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $schedules = Schedule::where('user_id', $user_id)
      ->orderBy('day_of_week')
      ->orderBy('time')
      ->get();

    // Organize by days of week
    $weeklySchedule = [];
    for ($day = 1; $day <= 7; $day++) {
      $daySchedules = $schedules->where('day_of_week', $day);
      $weeklySchedule[] = [
        'day_of_week' => $day,
        'day_name' => Schedule::getDayNames()[$day],
        'day_abbreviation' => Schedule::getDayAbbreviations()[$day],
        'times' => $daySchedules->map(function ($schedule) use ($day) {
          return [
            'id' => $schedule->id,
            'day_of_week' => $day,
            'time' => $schedule->time,
            'time_formatted' => Carbon::parse($schedule->time)->format('H:i'),
            'time_12h' => Carbon::parse($schedule->time)->format('h:i A'),
          ];
        })->values()
      ];
    }

    return response()->json(['data' => $weeklySchedule], 200);
  }

  /**
   * Obtener horarios cercanos para colas
   */
  public function getClosestSchedule($user_id)
  {
    $validator = Validator::make(['user_id' => $user_id], [
      'user_id' => 'required|integer|exists:users,id',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $closestSchedule = Schedule::getClosestSchedule($user_id);
    
    if (!$closestSchedule) {
      return response()->json(['message' => 'No schedules found for this user'], 404);
    }

    $nextOccurrence = $closestSchedule->getNextOccurrence();
    
    $response = [
      'schedule' => $closestSchedule,
      'next_occurrence' => $nextOccurrence->format('Y-m-d H:i:s'),
      'next_occurrence_formatted' => $nextOccurrence->format('l j F Y, g:i A'),
      'days_until' => $nextOccurrence->diffInDays(Carbon::now()),
      'hours_until' => $nextOccurrence->diffInHours(Carbon::now()),
    ];

    return response()->json(['data' => $response], 200);
  }

  /**
   * Obtiene las próximas fechas disponibles para los horarios de un usuario
   */
  public function getNextAvailableDates($user_id, $limit = 10)
  {
    $validator = Validator::make(['user_id' => $user_id], [
      'user_id' => 'required|integer|exists:users,id',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $schedules = Schedule::where('user_id', $user_id)
      ->orderBy('day_of_week')
      ->orderBy('time')
      ->get();

    if ($schedules->isEmpty()) {
      return response()->json(['data' => [], 'message' => 'No schedules found for this user'], 200);
    }

    $availableDates = $this->queueScheduleService->getNextAvailableDates($schedules, $limit);

    return response()->json([
      'data' => $availableDates,
      'message' => 'Next available dates retrieved successfully'
    ], 200);
  }
  
  /**
   * Obtiene el próximo horario disponible para un usuario
   */
  public function getNextAvailableSchedule($user_id)
  {
    $validator = Validator::make(['user_id' => $user_id], [
      'user_id' => 'required|integer|exists:users,id',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $nextSchedule = $this->queueScheduleService->findNextAvailableSchedule($user_id);
    
    if (!$nextSchedule) {
      return response()->json(['message' => 'No schedules found for this user'], 404);
    }

    $nextOccurrence = $this->queueScheduleService->getNextAvailableDate($nextSchedule);
    
    $response = [
      'schedule' => $nextSchedule,
      'next_occurrence' => $nextOccurrence->format('Y-m-d H:i:s'),
      'next_occurrence_formatted' => $nextOccurrence->translatedFormat('l j F Y, g:i A'),
      'days_until' => $nextOccurrence->diffInDays(Carbon::now()),
      'hours_until' => $nextOccurrence->diffInHours(Carbon::now()),
      'is_available_today' => $this->queueScheduleService->isScheduleAvailableToday($nextSchedule),
    ];

    return response()->json(['data' => $response], 200);
  }
 
}

