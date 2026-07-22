@php
    $isEdit = isset($event);
    $formAction = $isEdit ? route('events.update', $event) : route('events.store');
@endphp

<div class="modal-overlay" id="eventModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">{{ $isEdit ? 'Edit Event Details' : 'Create New Event' }}</h3>
            <button type="button" class="modal-close" data-close-modal="eventModal">&times;</button>
        </div>

        <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" class="modal-body">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="form-grid">
                <div class="form-group form-group--full">
                    <label for="event_name">Event Name</label>
                    <input type="text" id="event_name" name="event_name" value="{{ old('event_name', $isEdit ? $event->event_name : '') }}" required>
                </div>

                <div class="form-group">
                    <label for="event_type">Event Type</label>
                    <select id="event_type" name="event_type" required>
                        @foreach (['Wedding', 'Birthday Party', 'Corporate Event', 'Baby Shower', 'Graduation', 'Custom'] as $type)
                            <option value="{{ $type }}" @selected(old('event_type', $isEdit ? $event->event_type : '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" id="customEventTypeGroup">
                    <label for="custom_event_type">Custom Type</label>
                    <input type="text" id="custom_event_type" name="custom_event_type" value="{{ old('custom_event_type', $isEdit ? $event->custom_event_type : '') }}">
                </div>

                <div class="form-group">
                    <label for="event_date">Event Date</label>
                    <input type="date" id="event_date" name="event_date" value="{{ old('event_date', $isEdit ? optional($event->event_date)->toDateString() : now()->addDays(2)->toDateString()) }}" required>
                </div>

                <div class="form-group">
                    <label for="event_time">Event Time</label>
                    <input type="time" id="event_time" name="event_time" value="{{ old('event_time', $isEdit ? $event->event_time : '') }}">
                </div>

                <div class="form-group">
                    <label for="guest_count">Confirmed Guests</label>
                    <input type="number" min="0" id="guest_count" name="guest_count" value="{{ old('guest_count', $isEdit ? $event->guest_count : 0) }}">
                </div>

                <div class="form-group">
                    <label for="max_guests">Guest Capacity</label>
                    <input type="number" min="0" id="max_guests" name="max_guests" value="{{ old('max_guests', $isEdit ? $event->max_guests : '') }}">
                </div>

                <div class="form-group">
                    <label for="venue_name">Venue Name</label>
                    <input type="text" id="venue_name" name="venue_name" value="{{ old('venue_name', $isEdit ? $event->venue_name : '') }}">
                </div>

                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" value="{{ old('location', $isEdit ? $event->location : '') }}">
                </div>

                <div class="form-group form-group--full">
                    <label for="venue_image">Venue Image</label>
                    <input type="file" id="venue_image" name="venue_image" accept="image/*">
                    @if ($isEdit && $event->venue_image)
                        <img src="{{ asset('storage/'.$event->venue_image) }}" alt="Venue preview" class="venue-image-preview">
                    @endif
                </div>

                <div class="form-group">
                    <label for="total_budget">Total Budget</label>
                    <input type="number" min="0" step="0.01" id="total_budget" name="total_budget" value="{{ old('total_budget', $isEdit ? $event->total_budget : 0) }}">
                </div>

                <div class="form-group">
                    <label for="budget_spent">Budget Spent</label>
                    <input type="number" min="0" step="0.01" id="budget_spent" name="budget_spent" value="{{ old('budget_spent', $isEdit ? $event->budget_spent : 0) }}">
                </div>

                <div class="form-group">
                    <label for="currency">Currency</label>
                    <select id="currency" name="currency" required>
                        @foreach (['PKR', 'USD'] as $currency)
                            <option value="{{ $currency }}" @selected(old('currency', $isEdit ? $event->currency : 'PKR') === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($isEdit)
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            @foreach (['Planning', 'In Progress', 'Completed', 'Cancelled'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $event->status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="form-group form-group--full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3">{{ old('description', $isEdit ? $event->description : '') }}</textarea>
                </div>
            </div>

            @if ($errors->any())
                <div class="form-errors">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal="eventModal">Cancel</button>
                <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Save Changes' : 'Create Event' }}</button>
            </div>
        </form>

        @if ($isEdit)
            <form action="{{ route('events.destroy', $event) }}" method="POST" class="modal-delete-form"
                  onsubmit="return confirm('Delete this event and all of its tasks? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-delete-event">
                    <i class="fas fa-trash"></i> Delete Event
                </button>
            </form>
        @endif
    </div>
</div>
