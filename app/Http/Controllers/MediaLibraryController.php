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
        $tab = in_array($request->query('tab'), MediaItem::TYPES, true)
            ? $request->query('tab')
            : 'video';
        $q = trim((string) $request->query('q', ''));

        $items = MediaItem::where('type', $tab)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('title', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->get();

        return view('mediebibliotek.index', [
            'tab' => $tab,
            'q' => $q,
            'items' => $items,
            'isOwner' => $request->user()->isOwner(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type');
        abort_unless(in_array($type, MediaItem::TYPES, true), 422);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
        $rules['file'] = match ($type) {
            'video' => ['required', 'file', 'mimes:mp4,mov,avi,webm,m4v,mkv', 'max:512000'],
            'audio' => ['required', 'file', 'mimes:mp3,wav,m4a,ogg,aac,flac', 'max:51200'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        };

        $messages = [
            'title.required' => 'Titel er påkrævet.',
            'file.required' => 'Vælg en fil.',
            'file.mimes' => 'Filtypen understøttes ikke.',
            'file.max' => 'Filen er for stor.',
            'file.image' => 'Filen er ikke et billede.',
        ];

        $data = $request->validate($rules, $messages);

        $file = $request->file('file');
        $subdir = $type . 's/' . now()->format('Y/m'); // videos|audios|images — kept simple
        $name = Str::ulid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs($subdir, $name, self::DISK);

        if (!$path) {
            return back()->withInput()->withErrors(['file' => 'Kunne ikke gemme filen på serveren.']);
        }

        $item = MediaItem::create([
            'type' => $type,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $type === 'video' ? null : $path,
            'video_path' => $type === 'video' ? $path : null,
            'video_processing_status' => $type === 'video' ? 'pending' : null,
            'user_id' => $request->user()->id,
        ]);

        if ($type === 'video') {
            ProcessVideoJob::dispatch(MediaItem::class, $item->id, $path, self::DISK, true);
        }

        return redirect()
            ->route('media.index', ['tab' => $type])
            ->with('status', 'Medie uploadet.');
    }

    public function destroy(Request $request, MediaItem $mediaItem): RedirectResponse
    {
        $type = $mediaItem->type;
        $mediaItem->deleteFiles();
        $mediaItem->delete();

        return redirect()
            ->route('media.index', ['tab' => $type])
            ->with('status', 'Medie slettet.');
    }
}
