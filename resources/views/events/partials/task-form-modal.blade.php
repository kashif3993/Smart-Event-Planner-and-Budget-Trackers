{{-- Add Task modal --}}
<div class="modal-overlay" id="taskModalAdd">
    <div class="modal-dialog modal-dialog--sm">
        <div class="modal-header">
            <h3 class="modal-title">Add Task</h3>
            <button type="button" class="modal-close" data-close-modal="taskModalAdd">&times;</button>
        </div>

        <form action="{{ route('events.tasks.store', $event) }}" method="POST" class="modal-body">
            @csrf

            <div class="form-grid">
                <div class="form-group form-group--full">
                    <label for="add_task_name">Task Name</label>
                    <input type="text" id="add_task_name" name="task_name" required>
                </div>

                <div class="form-group">
                    <label for="add_phase">Phase</label>
                    <select id="add_phase" name="phase" required>
                        <option value="Pre-Planning">Pre-Planning</option>
                        <option value="Preparation">Preparation</option>
                        <option value="Day-Of">Day-Of</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="add_priority">Priority</label>
                    <select id="add_priority" name="priority" required>
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="add_due_date">Due Date</label>
                    <input type="date" id="add_due_date" name="due_date">
                </div>

                <div class="form-group">
                    <label for="add_dependency_task_id">Depends On</label>
                    <select id="add_dependency_task_id" name="dependency_task_id">
                        <option value="">None</option>
                        @foreach ($allEventTasks as $otherTask)
                            <option value="{{ $otherTask->id }}">{{ $otherTask->task_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group form-group--full">
                    <label for="add_notes">Notes</label>
                    <textarea id="add_notes" name="notes" rows="3"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal="taskModalAdd">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Task</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Task modal - fields populated by tasks.js from the clicked row's data attributes --}}
<div class="modal-overlay" id="taskModalEdit">
    <div class="modal-dialog modal-dialog--sm">
        <div class="modal-header">
            <h3 class="modal-title">Edit Task</h3>
            <button type="button" class="modal-close" data-close-modal="taskModalEdit">&times;</button>
        </div>

        <form id="taskEditForm" action="{{ route('events.tasks.update', [$event, '__TASK__']) }}" method="POST" class="modal-body">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group form-group--full">
                    <label for="edit_task_name">Task Name</label>
                    <input type="text" id="edit_task_name" name="task_name" required>
                </div>

                <div class="form-group">
                    <label for="edit_phase">Phase</label>
                    <select id="edit_phase" name="phase" required>
                        <option value="Pre-Planning">Pre-Planning</option>
                        <option value="Preparation">Preparation</option>
                        <option value="Day-Of">Day-Of</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_priority">Priority</label>
                    <select id="edit_priority" name="priority" required>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_due_date">Due Date</label>
                    <input type="date" id="edit_due_date" name="due_date">
                </div>

                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Skipped">Skipped</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_dependency_task_id">Depends On</label>
                    <select id="edit_dependency_task_id" name="dependency_task_id">
                        <option value="">None</option>
                        @foreach ($allEventTasks as $otherTask)
                            <option value="{{ $otherTask->id }}" data-task-option-id="{{ $otherTask->id }}">{{ $otherTask->task_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group form-group--full">
                    <label for="edit_notes">Notes</label>
                    <textarea id="edit_notes" name="notes" rows="3"></textarea>
                </div>
            </div>

            <div class="modal-footer modal-footer--split">
                <button type="submit" form="taskDeleteForm" class="btn-delete-event" style="width:auto;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <div>
                    <button type="button" class="btn btn-outline" data-close-modal="taskModalEdit">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>

        <form id="taskDeleteForm" action="{{ route('events.tasks.destroy', [$event, '__TASK__']) }}" method="POST"
              onsubmit="return confirm('Remove this task?')" style="display:none;">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
