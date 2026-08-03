{{-- Edit Event modal (events index cards) - fields populated by events.js
     from the clicked card's data-event-* attributes, the same pattern
     task-form-modal.blade.php uses for the Edit Task modal. --}}
<div class="modal-overlay" id="eventModalEdit">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit Event Details</h3>
            <button type="button" class="modal-close" data-close-modal="eventModalEdit">&times;</button>
        </div>

        <form id="eventEditForm" action="{{ route('events.update', '__EVENT__') }}" method="POST" enctype="multipart/form-data" class="modal-body">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-group form-group--full">
                    <label for="edit_event_name">Event Name</label>
                    <input type="text" id="edit_event_name" name="event_name" required>
                </div>

                <div class="form-group">
                    <label for="edit_event_type">Event Type</label>
                    <select id="edit_event_type" name="event_type" required>
                        @foreach (['Wedding', 'Birthday Party', 'Corporate Event', 'Baby Shower', 'Graduation', 'Custom'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="editCustomEventTypeGroup">
                    <label for="edit_custom_event_type">Custom Type</label>
                    <input type="text" id="edit_custom_event_type" name="custom_event_type">
                </div>

                <div class="form-group">
                    <label for="edit_event_date">Event Date</label>
                    <input type="date" id="edit_event_date" name="event_date" required>
                </div>

                <div class="form-group">
                    <label for="edit_event_time">Event Time</label>
                    <input type="time" id="edit_event_time" name="event_time">
                </div>

                <div class="form-group">
                    <label for="edit_guest_count">Confirmed Guests</label>
                    <input type="number" min="0" id="edit_guest_count" name="guest_count">
                </div>

                <div class="form-group">
                    <label for="edit_max_guests">Guest Capacity</label>
                    <input type="number" min="0" id="edit_max_guests" name="max_guests">
                </div>

                <div class="form-group">
                    <label for="edit_venue_name">Venue Name</label>
                    <input type="text" id="edit_venue_name" name="venue_name">
                </div>

                <div class="form-group">
                    <label for="edit_location">Location</label>
                    <input type="text" id="edit_location" name="location">
                </div>

                <div class="form-group form-group--full">
                    <label for="edit_venue_image">Venue Image</label>
                    <input type="file" id="edit_venue_image" name="venue_image" accept="image/*">
                    <img alt="Venue preview" class="venue-image-preview" id="edit_venue_image_preview" hidden>
                </div>

                <div class="form-group">
                    <label for="edit_total_budget">Total Budget</label>
                    <input type="number" min="0" step="0.01" id="edit_total_budget" name="total_budget">
                </div>

                <div class="form-group">
                    <label for="edit_budget_spent">Budget Spent</label>
                    <input type="number" min="0" step="0.01" id="edit_budget_spent" name="budget_spent">
                </div>

                <div class="form-group">
                    <label for="edit_currency">Currency</label>
                    <select id="edit_currency" name="currency" required>
                        @foreach (['PKR', 'USD'] as $currency)
                            <option value="{{ $currency }}">{{ $currency }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" required>
                        @foreach (['Planning', 'In Progress', 'Completed', 'Cancelled'] as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group form-group--full">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
            </div>

            <div class="modal-footer modal-footer--split">
                <button type="submit" form="eventDeleteForm" class="btn-delete-event" style="width:auto;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <div>
                    <button type="button" class="btn btn-outline" data-close-modal="eventModalEdit">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </div>
        </form>

        <form id="eventDeleteForm" action="{{ route('events.destroy', '__EVENT__') }}" method="POST"
              onsubmit="return confirm('Delete this event and all of its tasks? This cannot be undone.')" style="display:none;">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
