<?php

namespace App\Http\Controllers;

use App\Exceptions\AiTaskGeneratorNotConfiguredException;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Event;
use App\Models\Task;
use App\Services\AiTaskGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($event);

        $event->tasks()->create($request->validated() + [
            'source' => 'Manual',
            'status' => 'Pending',
        ]);

        return back()->with('success', 'Task added.');
    }

    public function update(UpdateTaskRequest $request, Event $event, Task $task): RedirectResponse
    {
        $this->authorizeTask($event, $task);

        $task->update($request->validated());

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Event $event, Task $task): RedirectResponse
    {
        $this->authorizeTask($event, $task);

        $task->delete();

        return back()->with('success', 'Task removed.');
    }

    public function toggleStatus(Event $event, Task $task): JsonResponse
    {
        $this->authorizeTask($event, $task);

        $task->status = $task->status === 'Completed' ? 'Pending' : 'Completed';
        $task->save();

        return response()->json(['status' => $task->status]);
    }

    public function generateAi(Event $event, AiTaskGeneratorService $service): JsonResponse
    {
        $this->authorizeEvent($event);

        // The AI call can legitimately take longer than PHP's default execution
        // limit once retries are factored in — extend it rather than let this
        // request die with a fatal error instead of the JSON response tasks.js expects.
        set_time_limit(120);

        try {
            $result = $service->generateTasks($event);
        } catch (AiTaskGeneratorNotConfiguredException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report($e);
            $response = $e->response->json();
            $apiMessage = $response['error']['message'] ?? 'AI task generation failed (API Error).';
            return response()->json(['success' => false, 'message' => 'Google API Error: ' . $apiMessage], 502);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'AI task generation failed. Please try again later.'], 502);
        }

        if (empty($result['tasks'])) {
            return response()->json(['success' => false, 'message' => 'The AI did not return any tasks.'], 422);
        }

        $created = $service->createTasks($event, $result['tasks']);

        if ($result['insight']) {
            $event->update(['ai_insight' => $result['insight']]);
        }

        return response()->json([
            'success' => true,
            'message' => $created->count().' AI-generated tasks added.',
        ]);
    }

    public function suggestAi(Event $event, AiTaskGeneratorService $service): JsonResponse
    {
        $this->authorizeEvent($event);

        set_time_limit(60);

        try {
            $task = $service->suggestTask($event);
        } catch (AiTaskGeneratorNotConfiguredException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            report($e);
            $response = $e->response->json();
            $apiMessage = $response['error']['message'] ?? 'AI task suggestion failed (API Error).';
            return response()->json(['success' => false, 'message' => 'Google API Error: ' . $apiMessage], 502);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'AI task suggestion failed. Please try again later.'], 502);
        }

        if (empty($task)) {
            return response()->json(['success' => false, 'message' => 'The AI could not suggest a task.'], 422);
        }

        return response()->json(['success' => true, 'task' => $task]);
    }

    protected function authorizeEvent(Event $event): void
    {
        abort_unless($event->user_id === Auth::id(), 403);
    }

    protected function authorizeTask(Event $event, Task $task): void
    {
        $this->authorizeEvent($event);

        abort_unless($task->event_id === $event->id, 404);
    }
}
