<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVideoJob;
use App\Models\Course;
use App\Models\CourseMedia;
use App\Models\MediaItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseMediaController extends Controller
{
    private const DISK = 'media';

    public function index(Request $request, Course $course)
    {
        $user = $request->user();
        $canManage = $this->canManage($user, $course);
        abort_unless($canManage || $user->enrolledIn($course), 403);

        $items = CourseMedia::with(['mediaItem', 'user'])
            ->where('course_id', $course->id)
            ->orderByDesc('id')
            ->get();

        // Members only get the tab when there's content — direct links included.
        if (!$canManage && $items->isEmpty()) {
            return redirect()->route('courses.show', $course);
        }

        return view('courses.media', [
            'course' => $course,
            'items' => $items,
            'canManage' => $canManage,
            'title' => $course->title,
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $course), 403);

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
            'media_item_id' => ['nullable', 'integer', 'exists:media_items,id'],
            'file' => ['nullable', 'file', 'required_without:media_item_id'],
        ], [
            'file.required_without' => 'Vælg en fil eller et medie fra biblioteket.',
        ]);

        if (!empty($data['media_item_id'])) {
            $item = MediaItem::findOrFail($data['media_item_id']);
            CourseMedia::create([
                'course_id' => $course->id,
                'user_id' => $request->user()->id,
                'media_item_id' => $item->id,
                'type' => $item->type,
                'comment' => $data['comment'] ?? null,
            ]);
            return redirect()->route('courses.media', $course)->with('status', 'Medie tilføjet.');
        }

        $file = $request->file('file');
        $type = MediaItem::detectUploadType($file);
        if ($type === null) {
            return back()->withInput()->withErrors([
                'file' => 'Filtypen understøttes ikke. Du kan uploade video, lyd eller billeder.',
            ]);
        }

        $request->validate(['file' => MediaItem::uploadRules($type)], [
            'file.mimes' => 'Filtypen understøttes ikke.',
            'file.image' => 'Filen er ikke et billede.',
            'file.max' => 'Filen er for stor.',
        ]);

        $subdir = 'course-media/' . now()->format('Y/m');
        $name = Str::ulid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs($subdir, $name, self::DISK);

        if (!$path) {
            return back()->withInput()->withErrors(['file' => 'Kunne ikke gemme filen på serveren.']);
        }

        $media = CourseMedia::create([
            'course_id' => $course->id,
            'user_id' => $request->user()->id,
            'type' => $type,
            'file_path' => $type === 'video' ? null : $path,
            'video_path' => $type === 'video' ? $path : null,
            'video_processing_status' => $type === 'video' ? 'pending' : null,
            'comment' => $data['comment'] ?? null,
        ]);

        if ($type === 'video') {
            ProcessVideoJob::dispatch(CourseMedia::class, $media->id, $path, self::DISK, true);
        }

        return redirect()->route('courses.media', $course)->with('status', 'Medie tilføjet.');
    }

    public function destroy(Request $request, Course $course, CourseMedia $courseMedia): RedirectResponse
    {
        abort_unless($this->canManage($request->user(), $course), 403);
        abort_unless($courseMedia->course_id === $course->id, 404);

        $courseMedia->deleteFiles();
        $courseMedia->delete();

        return redirect()->route('courses.media', $course)->with('status', 'Medie slettet.');
    }

    private function canManage($user, Course $course): bool
    {
        return $user && ($user->isOwner() || $course->hasTrainer($user));
    }
}
