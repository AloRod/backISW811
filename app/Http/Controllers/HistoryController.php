<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\History;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

class HistoryController extends Controller
{
    //obtener datos
  public function index()
  {
    $histories = History::all();
    return response()->json(['data' => $histories], 200);
  }
  //guardar
  public function store(Request $request)
  {
    $post = Post::create($request->all());

    $historyData = $request->only('date', 'time', 'post_id', 'status');
    $historyData['post_id'] = $post->id;

    $validator = Validator::make($historyData, [
      'post_id' => 'required|integer',
      'status' => 'required|in:immediate,scheduled,queue',
      'date' => 'nullable',
      'time' => 'nullable',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    // se encarga de enviar el post a las redes sociales si el estado es 'immediate'
    if ($request->status == 'immediate') {
      $post = new PostController;
      $post->sendToNetworks($request->user_id, $request->social_network, $request->post_text);
    }

    $history = History::create($historyData);

    return response()->json(['data' => ['post' => $post, 'history' => $history]], 201);
  }

  public function updateStatus(Request $request, $id)
  {
    $data = $request->only('status');

    $validator = Validator::make($data, [
      'status' => 'required',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $history = History::find($id);

    if (!$history) {
      return response()->json(['message' => 'History not found'], 404);
    }

    $history->update($request->all());

    return response()->json(['data' => $history], 200);
  }

  public function getByUserId($userId)
  {
    $histories = History::join('posts', 'posts.id', '=', 'histories.post_id')
      ->select('posts.id', 'posts.post_text', 'histories.status', 'posts.social_network')
      ->selectRaw("CONCAT(histories.date, ' ', histories.time) AS date")
      ->where('posts.user_id', $userId)
      ->orderBy('histories.created_at')
      ->get();

    return response()->json(['data' => $histories], 200);
  }

  //Pendiente
public function getQueueByUserId($user_id)
  {
    // Get all queue posts for the user
    $queuePosts = History::join('posts', 'posts.id', '=', 'histories.post_id')
      ->select(
        'histories.id as history_id',
        'posts.post_text', 
        'posts.social_network', 
        'histories.status', 
        'posts.user_id', 
        'histories.created_at'
      )
      ->where('posts.user_id', $user_id)
      ->where('histories.status', 'queue')
      ->orderBy('histories.created_at')
      ->get();

    // Get user's schedules
    $schedules = Schedule::where('user_id', $user_id)
      ->orderBy('day_of_week')
      ->orderBy('time')
      ->get();

    if ($schedules->isEmpty()) {
      return response()->json(['data' => [], 'message' => 'No schedules found for this user'], 200);
    }

    $results = [];
    $currentDateTime = Carbon::now();
    $scheduleIndex = 0;

    foreach ($queuePosts as $post) {
      // Find the next available schedule
      $nextSchedule = $this->findNextAvailableSchedule($schedules, $currentDateTime, $scheduleIndex);
      
      if ($nextSchedule) {
        $nextOccurrence = $nextSchedule->getNextOccurrence($currentDateTime);
        
        // Asegurar que la fecha calculada no sea en el pasado
        if ($nextOccurrence->gt($currentDateTime)) {
          $results[] = [
            'history_id' => $post->history_id,
            'post_text' => $post->post_text,
            'social_network' => $post->social_network,
            'status' => $post->status,
            'created_at' => $post->created_at,
            'scheduled_for' => $nextOccurrence->format('Y-m-d H:i:s'),
            'scheduled_for_formatted' => $nextOccurrence->format('l, F j, Y \a\t g:i A'),
            'schedule_info' => [
              'day_name' => $nextSchedule->day_name,
              'time' => $nextSchedule->time,
              'time_formatted' => Carbon::parse($nextSchedule->time)->format('H:i'),
            ]
          ];

          // Move to next schedule for next post
          $scheduleIndex++;
          if ($scheduleIndex >= $schedules->count()) {
            $scheduleIndex = 0; // Reset to first schedule
          }
          
          // Update current datetime to the next occurrence for next iteration
          $currentDateTime = $nextOccurrence->copy()->addMinute();
        }
      }
    }

    return response()->json(['data' => $results], 200);
  }
  
  /**
   * Find the next available schedule from a given datetime
   */
  private function findNextAvailableSchedule($schedules, $fromDateTime, $startIndex = 0)
  {
    $totalSchedules = $schedules->count();
    
    // Try schedules starting from startIndex
    for ($i = 0; $i < $totalSchedules; $i++) {
      $index = ($startIndex + $i) % $totalSchedules;
      $schedule = $schedules[$index];
      $nextOccurrence = $schedule->getNextOccurrence($fromDateTime);
      
      // If this schedule is in the future, it's available
      if ($nextOccurrence->gt($fromDateTime)) {
        return $schedule;
      }
    }
    
    // If no future schedules found, return the first one (will be next week)
    return $schedules->first();
  }
  
  public function sendScheduledPosts()
  {
    $datetime = Carbon::now();
    $histories = History::with('post')
      ->where('status', 'scheduled')
      ->whereRaw("DATE_FORMAT(CONCAT(date, ' ', `time`), '%Y-%m-%d %H:%i') <= DATE_FORMAT(?, '%Y-%m-%d %H:%i')", [$datetime->format('Y-m-d H:i')])
      ->get();
    
    foreach ($histories as $history) {
      $post = new PostController;
      $post->sendToNetworks(
        $history->post->user_id,
        $history->post->social_network,
        $history->post->post_text
      );
      
      // Mark the post as immediate
      $history->status = 'immediate';
      $history->save();
    }
  }
  
  public function sendQueuePosts()
  {
    $currentDateTime = Carbon::now();
    Log::info('sendQueuePosts started at: ' . $currentDateTime->format('Y-m-d H:i:s'));
    
    // Get all queue posts grouped by user
    $queuePosts = History::join('posts', 'posts.id', '=', 'histories.post_id')
      ->select(
        'histories.id as history_id',
        'posts.post_text', 
        'posts.social_network', 
        'histories.status', 
        'posts.user_id', 
        'histories.created_at'
      )
      ->where('histories.status', 'queue')
      ->orderBy('histories.created_at')
      ->get()
      ->groupBy('user_id');

    Log::info('Found ' . count($queuePosts) . ' users with queue posts');

    foreach ($queuePosts as $userId => $userPosts) {
      Log::info("Processing user $userId with " . count($userPosts) . " queue posts");
      
      // Get user's schedules
      $schedules = Schedule::where('user_id', $userId)
        ->orderBy('day_of_week')
        ->orderBy('time')
        ->get();

      if ($schedules->isEmpty()) {
        Log::warning("No schedules found for user $userId");
        continue; // Skip users without schedules
      }

      $scheduleIndex = 0;
      $currentTime = $currentDateTime->copy();

      foreach ($userPosts as $post) {
        // Find the next available schedule
        $nextSchedule = $this->findNextAvailableSchedule($schedules, $currentTime, $scheduleIndex);
        
        if ($nextSchedule) {
          $nextOccurrence = $nextSchedule->getNextOccurrence($currentTime);
          
          Log::info("Post {$post->history_id} scheduled for: " . $nextOccurrence->format('Y-m-d H:i:s'));
          
          // Check if it's time to publish this post (next occurrence is in the past or now)
          if ($nextOccurrence->lte($currentDateTime)) {
            Log::info("Publishing post {$post->history_id} now");
            
            try {
              // Send the post
              $postController = new PostController;
              $postController->sendToNetworks(
                $userId,
                $post->social_network,
                $post->post_text
              );

              // Mark the post as immediate
              $history = History::find($post->history_id);
              $history->status = 'immediate';
              $history->date = $nextOccurrence->format('Y-m-d');
              $history->time = $nextOccurrence->format('H:i:s');
              $history->save();

              Log::info("Successfully published and updated post {$post->history_id}");
            } catch (\Exception $e) {
              Log::error("Error publishing post {$post->history_id}: " . $e->getMessage());
            }

            // Move to next schedule for next post
            $scheduleIndex++;
            if ($scheduleIndex >= $schedules->count()) {
              $scheduleIndex = 0;
            }
            
            // Update current time for next iteration
            $currentTime = $nextOccurrence->copy()->addMinute();
          } else {
            Log::info("Post {$post->history_id} not ready yet, scheduled for: " . $nextOccurrence->format('Y-m-d H:i:s'));
          }
        }
      }
    }
    
    Log::info('sendQueuePosts completed');
  }

}
