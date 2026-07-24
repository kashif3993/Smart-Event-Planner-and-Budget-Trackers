document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // ----- Populate the Edit Task modal from the clicked row's data attributes -----
    const editForm = document.getElementById('taskEditForm');
    const deleteForm = document.getElementById('taskDeleteForm');
    // Captured once, before any row is edited, so repeated edits always rebuild
    // the URL from the original "__TASK__" template instead of an already-replaced one.
    const editFormActionTemplate = editForm ? editForm.action : null;
    const deleteFormActionTemplate = deleteForm ? deleteForm.action : null;

    document.querySelectorAll('.task-edit-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const row = button.closest('.task-row');
            if (!row || !editForm) return;

            const taskId = row.dataset.taskId;

            if (editFormActionTemplate) {
                editForm.action = editFormActionTemplate.replace('__TASK__', taskId);
            }
            if (deleteForm && deleteFormActionTemplate) {
                deleteForm.action = deleteFormActionTemplate.replace('__TASK__', taskId);
            }

            editForm.querySelector('#edit_task_name').value = row.dataset.taskName || '';
            editForm.querySelector('#edit_phase').value = row.dataset.taskPhase || 'Pre-Planning';
            editForm.querySelector('#edit_priority').value = row.dataset.taskPriority || 'Medium';
            editForm.querySelector('#edit_status').value = row.dataset.taskStatus || 'Pending';
            editForm.querySelector('#edit_due_date').value = row.dataset.taskDueDate || '';
            editForm.querySelector('#edit_notes').value = row.dataset.taskNotes || '';

            const dependencySelect = editForm.querySelector('#edit_dependency_task_id');
            if (dependencySelect) {
                // A task can't depend on itself — hide its own entry from the list.
                dependencySelect.querySelectorAll('option[data-task-option-id]').forEach(function(opt) {
                    opt.hidden = opt.dataset.taskOptionId === taskId;
                });
                dependencySelect.value = row.dataset.taskDependencyId || '';
            }
        });
    });

    // ----- Status toggle switch (instant, no reload) -----
    document.querySelectorAll('.task-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            const url = toggle.getAttribute('data-toggle-url');
            const row = toggle.closest('.task-row');
            const statusText = row ? row.querySelector('.status-text') : null;

            toggle.disabled = true;

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    const isCompleted = data.status === 'Completed';
                    toggle.classList.toggle('is-on', isCompleted);
                    if (statusText) {
                        statusText.textContent = isCompleted ? 'Done' : data.status;
                    }
                    if (row) row.dataset.taskStatus = data.status;
                })
                .finally(function() { toggle.disabled = false; });
        });
    });

    // ----- Suggest with AI (Add/Edit Task modals) -----
    // Fetches one AI-suggested task and fills the currently open form's fields
    // so the user can review/tweak it before actually saving (Add/Save Changes).
    document.querySelectorAll('.ai-suggest-task-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const url = button.getAttribute('data-url');
            const prefix = button.getAttribute('data-target-prefix');
            const status = button.closest('.ai-suggest-row')?.querySelector('.ai-suggest-status');
            const form = button.closest('form');
            if (!url || !form) return;

            button.disabled = true;
            if (status) {
                status.textContent = 'Asking the AI for a task…';
                status.className = 'ai-suggest-status is-loading';
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            })
                .then(function(response) { return response.json().then(function(data) { return { ok: response.ok, data: data }; }); })
                .then(function(result) {
                    if (!result.ok || !result.data.success) {
                        if (status) {
                            status.textContent = (result.data && result.data.message) || 'AI suggestion failed.';
                            status.className = 'ai-suggest-status is-error';
                        }
                        return;
                    }

                    const task = result.data.task;
                    const nameField = form.querySelector('#' + prefix + 'task_name');
                    const phaseField = form.querySelector('#' + prefix + 'phase');
                    const priorityField = form.querySelector('#' + prefix + 'priority');
                    const dueDateField = form.querySelector('#' + prefix + 'due_date');
                    const notesField = form.querySelector('#' + prefix + 'notes');

                    if (nameField) nameField.value = task.task_name || '';
                    if (phaseField) phaseField.value = task.phase || 'Pre-Planning';
                    if (priorityField) priorityField.value = task.priority || 'Medium';
                    if (dueDateField) dueDateField.value = task.due_date || '';
                    if (notesField) notesField.value = task.notes || '';

                    if (status) {
                        status.textContent = 'Suggestion added below — review and save.';
                        status.className = 'ai-suggest-status is-success';
                    }
                })
                .catch(function() {
                    if (status) {
                        status.textContent = 'AI suggestion failed. Please try again.';
                        status.className = 'ai-suggest-status is-error';
                    }
                })
                .finally(function() {
                    button.disabled = false;
                });
        });
    });

    // ----- Generate with AI -----
    const generateBtn = document.getElementById('generateAiBtn');
    const statusBox = document.getElementById('aiGenerateStatus');

    if (generateBtn && statusBox) {
        generateBtn.addEventListener('click', function() {
            const url = generateBtn.getAttribute('data-url');

            generateBtn.disabled = true;
            statusBox.style.display = 'block';
            statusBox.className = 'ai-generate-status is-loading';
            statusBox.textContent = 'Asking the AI to build your task checklist... this can take up to a minute.';

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            })
                .then(function(response) { return response.json().then(function(data) { return { ok: response.ok, data: data }; }); })
                .then(function(result) {
                    if (result.ok && result.data.success) {
                        statusBox.className = 'ai-generate-status is-success';
                        statusBox.textContent = result.data.message + ' Reloading...';
                        setTimeout(function() { window.location.reload(); }, 1200);
                    } else {
                        statusBox.className = 'ai-generate-status is-error';
                        statusBox.textContent = result.data.message || 'AI task generation failed.';
                        generateBtn.disabled = false;
                    }
                })
                .catch(function() {
                    statusBox.className = 'ai-generate-status is-error';
                    statusBox.textContent = 'AI task generation failed. Please try again.';
                    generateBtn.disabled = false;
                });
        });
    }
});
