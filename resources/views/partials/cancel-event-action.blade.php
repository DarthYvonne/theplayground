{{-- Inline "Aflys"/"Genåbn" button sitting on top of an event card.
     Props: $course, $date (YYYY-MM-DD), $cancelled (bool)
     Caller must wrap with a position:relative ancestor. --}}
@if ($cancelled)
  <form method="POST" action="{{ route('trainer.cancellations.destroy', $course) }}" class="cal-event-action restore">
    @csrf @method('DELETE')
    <input type="hidden" name="occurrence_date" value="{{ $date }}">
    <button type="submit" title="Genåbn denne dag" onclick="return confirm('Genåbn {{ $course->title }} den {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}?');">
      <i class="fa-solid fa-rotate-left"></i>
    </button>
  </form>
@else
  <form method="POST" action="{{ route('trainer.cancellations.store', $course) }}" class="cal-event-action">
    @csrf
    <input type="hidden" name="occurrence_date" value="{{ $date }}">
    <button type="submit" title="Aflys denne dag" onclick="return confirm('Aflys {{ $course->title }} den {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}?');">
      <i class="fa-solid fa-ban"></i>
    </button>
  </form>
@endif
