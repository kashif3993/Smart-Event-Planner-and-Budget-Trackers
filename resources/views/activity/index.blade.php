@extends('layouts.app')

@section('title', 'Activity Log - EventPro')

@section('page_css')
    <link rel="stylesheet" href="{{ asset('css/activity.css') }}?v={{ time() }}">
@endsection

@section('content')
<div class="main-content">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">
                <i class="fas fa-history"></i>
                Activity Log
            </h1>
            <p class="page-subtitle">Track everything that has happened in your planner</p>
        </div>
        <div class="page-header-right">
            @if($activities->total() > 0)
                <form method="POST" action="{{ route('activity.clearAll') }}"
                      onsubmit="return confirm('Clear all activity logs? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    {{-- Filter Bar --}}
    <div class="activity-filter-bar">
        <a href="{{ route('activity.index') }}"
           class="filter-chip {{ $filter === 'all' ? 'active' : '' }}">
            All
        </a>
        @foreach($types as $type)
            <a href="{{ route('activity.index', ['type' => $type]) }}"
               class="filter-chip {{ $filter === $type ? 'active' : '' }}">
                {{ ucwords(str_replace('_', ' ', $type)) }}
            </a>
        @endforeach
    </div>

    {{-- Activity Timeline --}}
    @if($activities->isEmpty())
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-history"></i>
            </div>
            <h3>No Activity Yet</h3>
            <p>When you create events, add tasks, or update budgets — it will all show up here.</p>
            <a href="{{ route('events.index') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Your First Event
            </a>
        </div>
    @else
        <div class="activity-timeline">
            @foreach($activities as $activity)
                <div class="activity-item" data-color="{{ $activity->color ?? 'blue' }}">
                    {{-- Icon Bubble --}}
                    <div class="activity-icon activity-icon--{{ $activity->color ?? 'blue' }}">
                        <i class="fas {{ $activity->icon ?? 'fa-circle' }}"></i>
                    </div>

                    {{-- Content --}}
                    <div class="activity-body">
                        <p class="activity-description">{{ $activity->description }}</p>
                        <div class="activity-meta">
                            <span class="activity-badge activity-badge--{{ $activity->color ?? 'blue' }}">
                                {{ ucwords(str_replace('_', ' ', $activity->type)) }}
                            </span>
                            @if($activity->event && $activity->event->id)
                                <a href="{{ route('events.show', $activity->event) }}"
                                   class="activity-event-link">
                                    <i class="fas fa-calendar-alt"></i>
                                    {{ $activity->event->title }}
                                </a>
                            @endif
                            <span class="activity-time" title="{{ $activity->created_at->format('d M Y, h:i A') }}">
                                <i class="fas fa-clock"></i>
                                {{ $activity->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>

                    {{-- Delete Button --}}
                    <form method="POST"
                          action="{{ route('activity.destroy', $activity) }}"
                          class="activity-delete-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="activity-delete-btn"
                                title="Remove this log entry"
                                onclick="return confirm('Remove this entry?')">
                            <i class="fas fa-times"></i>
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($activities->hasPages())
            <div class="pagination-wrapper">
                {{ $activities->links() }}
            </div>
        @endif
    @endif

</div>
@endsection

@section('page_js')
    {{-- No extra JS needed for this page --}}
@endsection
