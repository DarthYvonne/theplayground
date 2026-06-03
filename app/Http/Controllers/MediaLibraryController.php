<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVideoJob;
use App\Models\MediaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaLibraryController extends Controller
{
    private const DISK = 'media';

    public function index(Request $request)
    {
        // Everything is shown at once, grouped by type. Search is done live in
        // the browser across all groups, so we don't filter server-side.
        $byType = MediaItem::orderByDesc('id')->get()->groupBy('type');

        return view('mediebibliotek.index', [
            'groups' => [
                'video' => $byType->get('video', collect()),
                'audio' => $byType->get('audio', collect()),
                'image' => $byType->get('image', collect()),
            ],
            'isOwner' => $request->user()->isOwner(),
        ]);
    }

    /** JSON list for the feed-composer media picker. */
    public function list(): JsonResponse
    {
        $items = MediaItem::orderByDesc('id')->get()->map->toPayload()->values();
        return response()->json(['items' => $items]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file'],
        ], [
            'title.required' => 'Titel er påkrævet.',
            'file.required' => 'Vælg en fil.',
        ]);

        $file = $request->file('file');
        $type = MediaItem::detectUploadType($file);

        if ($type === null) {
            return back()->withInput()->withErrors([
                'file' => 'Filtypen understøttes ikke. Du kan uploade video, lyd eller billeder.',
            ]);
        }

        // Type-specific limits, now that we know what kind of file it is.
        $request->validate([
            'file' => MediaItem::uploadRules($type),
        ], [
            'file.mimes' => 'Filtypen understøttes ikke.',
            'file.image' => 'Filen er ikke et billede.',
            'file.max' => 'Filen er for stor.',
        ]);

        $subdir = $type . 's/' . now()->format('Y/m'); // videos|audios|images
        $name = Str::ulid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs($subdir, $name, self::DISK);

        if (!$path) {
            return back()->withInput()->withErrors(['file' => 'Kunne ikke gemme filen på serveren.']);
        }

        $item = MediaItem::create([
            'type' => $type,
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'file_path' => $type === 'video' ? null : $path,
            'video_path' => $type === 'video' ? $path : null,
            'video_processing_status' => $type === 'video' ? 'pending' : null,
            'user_id' => $request->user()->id,
        ]);

        if ($type === 'video') {
            ProcessVideoJob::dispatch(MediaItem::class, $item->id, $path, self::DISK, true);
        }

        return redirect()->route('media.index')->with('status', 'Medie uploadet.');
    }

    public function update(Request $request, MediaItem $mediaItem): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'title.required' => 'Titel er påkrævet.',
        ]);

        $mediaItem->update($data);

        return redirect()->route('media.index')->with('status', 'Medie opdateret.');
    }

    public function destroy(Request $request, MediaItem $mediaItem): RedirectResponse
    {
        $mediaItem->deleteFiles();
        $mediaItem->delete();

        return redirect()->route('media.index')->with('status', 'Medie slettet.');
    }
}
