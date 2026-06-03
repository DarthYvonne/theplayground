<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVideoJob;
use App\Models\MediaItem;
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
        $type = $this->detectType($file);

        if ($type === null) {
            return back()->withInput()->withErrors([
                'file' => 'Filtypen understøttes ikke. Du kan uploade video, lyd eller billeder.',
            ]);
        }

        // Type-specific limits, now that we know what kind of file it is.
        $request->validate([
            'file' => match ($type) {
                'video' => ['file', 'mimes:mp4,mov,avi,webm,m4v,mkv', 'max:512000'],
                'audio' => ['file', 'mimes:mp3,wav,m4a,ogg,aac,flac', 'max:51200'],
                'image' => ['file', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
            },
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

    public function destroy(Request $request, MediaItem $mediaItem): RedirectResponse
    {
        $mediaItem->deleteFiles();
        $mediaItem->delete();

        return redirect()->route('media.index')->with('status', 'Medie slettet.');
    }

    /** Decide the media type from the uploaded file's content, falling back to extension. */
    private function detectType(\Illuminate\Http\UploadedFile $file): ?string
    {
        $mime = strtolower((string) $file->getMimeType());
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if (str_starts_with($mime, 'image/')) return 'image';

        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['mp4', 'mov', 'avi', 'webm', 'm4v', 'mkv'], true)) return 'video';
        if (in_array($ext, ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'flac'], true)) return 'audio';
        if (in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'webp'], true)) return 'image';

        return null;
    }
}
