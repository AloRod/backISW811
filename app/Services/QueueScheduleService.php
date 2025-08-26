<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Schedule;
use Illuminate\Support\Facades\Log;

class QueueScheduleService
{
    /**
     * Obtiene la próxima fecha disponible para un horario específico
     * 
     * @param Schedule $schedule El horario del modelo Schedule
     * @param Carbon|null $currentTime Tiempo actual (para testing)
     * @param bool $useDelay Si usar el delay de 1 minuto (true para envío, false para visualización)
     * @return Carbon
     */
    public function getNextAvailableDate(Schedule $schedule, Carbon $currentTime = null, bool $useDelay = true): Carbon
    {
        $now = $currentTime ?? Carbon::now();
        
        // Crear la fecha/hora programada para esta semana
        $scheduledDateTime = $this->createScheduledDateTime($schedule, $now);
        
        // Si el horario ya pasó esta semana, mover a la próxima semana
        if ($useDelay) {
            if ($scheduledDateTime->lt($now->copy()->subMinute())) {
                $scheduledDateTime->addWeek();
            }
        } else {
            if ($scheduledDateTime->lte($now)) {
                $scheduledDateTime->addWeek();
            }
        }
        
        return $scheduledDateTime;
    }
    
    /**
     * Obtiene todas las próximas fechas disponibles para múltiples horarios
     * 
     * @param \Illuminate\Support\Collection $schedules Colección de horarios
     * @param int $limit Límite de fechas a retornar
     * @param Carbon|null $currentTime Tiempo actual (para testing)
     * @return array
     */
    public function getNextAvailableDates($schedules, int $limit = 10, Carbon $currentTime = null): array
    {
        $availableDates = [];
        $now = $currentTime ?? Carbon::now();
        
        foreach ($schedules as $schedule) {
            // Obtener las próximas fechas para este horario durante varias semanas
            for ($week = 0; $week < ceil($limit / $schedules->count()) + 2; $week++) {
                $nextDate = $this->getNextAvailableDate($schedule, $now);
                $nextDate->addWeeks($week);
                
                $availableDates[] = [
                    'datetime' => $nextDate->copy(),
                    'schedule_id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'day_name' => $schedule->day_name,
                    'time' => $schedule->time,
                    'formatted' => $nextDate->format('Y-m-d H:i:s'),
                    'human_readable' => $nextDate->translatedFormat('l j F Y, g:i A')
                ];
            }
        }
        
        // Ordenar por fecha y retornar solo el límite solicitado
        usort($availableDates, function ($a, $b) {
            if ($a['datetime']->eq($b['datetime'])) {
                return 0;
            }
            return $a['datetime']->lt($b['datetime']) ? -1 : 1;
        });
        
        return array_slice($availableDates, 0, $limit);
    }
    
    /**
     * Verifica si un horario está disponible hoy
     * 
     * @param Schedule $schedule El horario del modelo Schedule
     * @param Carbon|null $currentTime Tiempo actual (para testing)
     * @param bool $useDelay Si usar el delay de 1 minuto (true para envío, false para visualización)
     * @return bool
     */
    public function isScheduleAvailableToday(Schedule $schedule, Carbon $currentTime = null, bool $useDelay = true): bool
    {
        $now = $currentTime ?? Carbon::now();
        $nextAvailable = $this->getNextAvailableDate($schedule, $now, $useDelay);
        
        if ($useDelay) {
            return $nextAvailable->isToday() && $nextAvailable->gte($now->copy()->subMinute());
        } else {
            return $nextAvailable->isToday() && $nextAvailable->gt($now);
        }
    }
    
    /**
     * Encuentra el próximo horario disponible para un usuario
     * 
     * @param int $userId ID del usuario
     * @param Carbon|null $currentTime Tiempo actual
     * @return Schedule|null
     */
    public function findNextAvailableSchedule(int $userId, Carbon $currentTime = null): ?Schedule
    {
        $now = $currentTime ?? Carbon::now();
        
        $schedules = Schedule::where('user_id', $userId)
            ->orderBy('day_of_week')
            ->orderBy('time')
            ->get();
            
        if ($schedules->isEmpty()) {
            return null;
        }
        
        $closestSchedule = null;
        $closestDateTime = null;
        
        foreach ($schedules as $schedule) {
            $nextOccurrence = $this->getNextAvailableDate($schedule, $now);
            
            if (!$closestDateTime || $nextOccurrence->lt($closestDateTime)) {
                $closestDateTime = $nextOccurrence;
                $closestSchedule = $schedule;
            }
        }
        
        return $closestSchedule;
    }
    
    /**
     * Obtiene todos los horarios ordenados por proximidad a la fecha actual
     * 
     * @param int $userId ID del usuario
     * @param Carbon|null $fromDateTime Fecha desde la cual buscar
     * @param bool $useDelay Si usar el delay de 1 minuto (true para envío, false para visualización)
     * @return array Array de horarios ordenados por proximidad
     */
    public function getOrderedAvailableSlots(int $userId, Carbon $fromDateTime = null, bool $useDelay = true): array
    {
        $now = $fromDateTime ?? Carbon::now();
        Log::info("getOrderedAvailableSlots - Fecha actual: " . $now->format('Y-m-d H:i:s'));
        
        $schedules = Schedule::where('user_id', $userId)
            ->orderBy('day_of_week')
            ->orderBy('time')
            ->get();
            
        if ($schedules->isEmpty()) {
            return [];
        }
        
        $availableSlots = [];
        
        foreach ($schedules as $schedule) {
            // Obtener la próxima ocurrencia para este horario
            $nextOccurrence = $this->getNextAvailableDate($schedule, $now, $useDelay);
            
            // Si la próxima ocurrencia es en el pasado, generar la de la siguiente semana
            // PERO permitir un margen de 1 minuto para que el horario esté disponible (solo si useDelay es true)
            if ($useDelay) {
                if ($nextOccurrence->lt($now->copy()->subMinute())) {
                    $nextOccurrence = $this->createScheduledDateTime($schedule, $now)->addWeek();
                }
            } else {
                if ($nextOccurrence->lte($now)) {
                    $nextOccurrence = $this->createScheduledDateTime($schedule, $now)->addWeek();
                }
            }
            
            $availableSlots[] = [
                'schedule' => $schedule,
                'nextOccurrence' => $nextOccurrence,
                'day_of_week' => $schedule->day_of_week,
                'time' => $schedule->time,
                'formatted_date' => $nextOccurrence->format('Y-m-d'),
                'formatted_time' => $nextOccurrence->format('H:i'),
                'human_readable' => $nextOccurrence->translatedFormat('l j F Y, g:i A')
            ];
            
            Log::info("Horario disponible: " . $schedule->day_name . " " . $schedule->time . " -> " . $nextOccurrence->format('Y-m-d H:i:s'));
        }
        
        // Ordenar por fecha/hora (más próximo primero)
        usort($availableSlots, function ($a, $b) {
            if ($a['nextOccurrence']->eq($b['nextOccurrence'])) {
                return 0;
            }
            return $a['nextOccurrence']->lt($b['nextOccurrence']) ? -1 : 1;
        });
        
        Log::info("Horarios ordenados por proximidad:");
        foreach ($availableSlots as $index => $slot) {
            Log::info(($index + 1) . ". " . $slot['schedule']->day_name . " " . $slot['time'] . " -> " . $slot['nextOccurrence']->format('Y-m-d H:i:s'));
        }
        
        return $availableSlots;
    }
    
    /**
     * Obtiene el próximo slot disponible para un post en cola usando la nueva lógica de ordenamiento
     * 
     * @param int $userId ID del usuario
     * @param Carbon $fromDateTime Fecha desde la cual buscar
     * @param int $postIndex Índice del post en cola (0, 1, 2, etc.)
     * @param bool $useDelay Si usar el delay de 1 minuto (true para envío, false para visualización)
     * @return array|null Array con schedule y nextOccurrence, o null si no hay horarios
     */
    public function getNextAvailableSlotByPostIndex(int $userId, Carbon $fromDateTime, int $postIndex = 0, bool $useDelay = true): ?array
    {
        $orderedSlots = $this->getOrderedAvailableSlots($userId, $fromDateTime, $useDelay);
        
        if (empty($orderedSlots)) {
            return null;
        }
        
        // Si hay más posts que horarios, usar el módulo para repetir
        $slotIndex = $postIndex % count($orderedSlots);
        $selectedSlot = $orderedSlots[$slotIndex];
        
        Log::info("Post #{$postIndex} asignado al horario: " . $selectedSlot['schedule']->day_name . " " . $selectedSlot['time'] . " -> " . $selectedSlot['nextOccurrence']->format('Y-m-d H:i:s'));
        
        return [
            'schedule' => $selectedSlot['schedule'],
            'nextOccurrence' => $selectedSlot['nextOccurrence']
        ];
    }
    
    /**
     * Obtiene el próximo slot disponible para un post en cola (método de compatibilidad)
     * 
     * @param int $userId ID del usuario
     * @param Carbon $fromDateTime Fecha desde la cual buscar
     * @param int $scheduleIndex Índice del horario a partir del cual buscar
     * @param bool $useDelay Si usar el delay de 1 minuto (true para envío, false para visualización)
     * @return array|null Array con schedule y nextOccurrence, o null si no hay horarios
     */
    public function getNextAvailableSlot(int $userId, Carbon $fromDateTime, int $scheduleIndex = 0, bool $useDelay = true): ?array
    {
        // Usar la nueva lógica de ordenamiento
        return $this->getNextAvailableSlotByPostIndex($userId, $fromDateTime, $scheduleIndex, $useDelay);
    }
    
    /**
     * Crea la fecha/hora programada para una semana específica
     * 
     * @param Schedule $schedule
     * @param Carbon $currentTime
     * @return Carbon
     */
    private function createScheduledDateTime(Schedule $schedule, Carbon $currentTime): Carbon
    {
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
        
        $scheduleDayNumber = $carbonDayMap[$schedule->day_of_week];
        
        // Crear la fecha/hora programada para esta semana
        $scheduledDateTime = $currentTime->copy()
            ->startOfWeek() // Ir al inicio de la semana (lunes)
            ->addDays($scheduleDayNumber === 0 ? 6 : $scheduleDayNumber - 1) // Ajustar al día correcto
            ->setTimeFromTimeString($schedule->time);
        
        return $scheduledDateTime;
    }
}