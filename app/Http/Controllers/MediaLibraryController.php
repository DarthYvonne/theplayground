<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVideoJob;
use App\Models\MediaItem;
use App\Models\Playlist;
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
        $byType = MediaItem::with('playlists')->orderByDesc('id')->get()->groupBy('type');

        return view('mediebibliotek.index', [
            'groups' => [
                'video' => $byType->get('video', collect()),
                'audio' => $byType->get('audio', collect()),
                'image' => $byType->get('image', collect()),
            ],
            'playlists' => Playlist::with('mediaItems')->orderBy('name')->get(),
            'isOwner' => $request->user()->isOwner(),
        ]);
    }

    /** JSON list for the shared media picker (feed + Hold). */
    public function list(): JsonResponse
    {
        $items = MediaItem::orderByDesc('id')->get()->map->toPayload()->values();
        $playlists = Playlist::with('mediaItems')->orderBy('name')->get()
            ->filter(fn ($p) => $p->mediaItems->isNotEmpty())
            ->map->toPayload()
            ->values();

        return response()->json(['items' => $items, 'playlists' => $playlists]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file'],
            'playlist_id' => ['nullable'],
            'new_playlist' => ['nullable', 'string', 'max:100'],
        ], [
            'title.required' => 'Titel er påkrævet.',
            'file.required' => 'Vælg en fil.',
        ]);

        // "Opret ny" needs a name — fail before any file lands on disk.
        if ($request->input('playlist_id') === 'new') {
            $request->validate([
                'new_playlist' => ['required', 'string', 'max:100'],
            ], [
                'new_playlist.required' => 'Skriv et navn til den nye playliste.',
            ]);
        }

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

        if ($playlist = $this->resolvePlaylist($request)) {
            $item->playlists()->attach($playlist->id);
        }

        return redirect()->route('media.index')->with('status', 'Medie uploadet.');
    }

    public function update(Request $request, MediaItem $mediaItem): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'playlist_id' => ['nullable'],
            'new_playlist' => ['nullable', 'string', 'max:100'],
        ], [
            'title.required' => 'Titel er påkrævet.',
        ]);

        if ($request->input('playlist_id') === 'new') {
            $request->validate([
                'new_playlist' => ['required', 'string', 'max:100'],
            ], [
                'new_playlist.required' => 'Skriv et navn til den nye playliste.',
            ]);
        }

        $mediaItem->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        $playlist = $this->resolvePlaylist($request);
        $mediaItem->playlists()->sync($playlist ? [$playlist->id] : []);

        return redirect()->route('media.index')->with('status', 'Medie opdateret.');
    }

    public function updatePlaylist(Request $request, Playlist $playlist): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('playlists', 'name')->ignore($playlist->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:8192'],
        ], [
            'name.required' => 'Navn er påkrævet.',
            'name.unique' => 'Der findes allerede en playliste med det navn.',
            'image.image' => 'Filen er ikke et billede.',
            'image.max' => 'Billedet må højst være 8 MB.',
        ]);

        if ($request->hasFile('image')) {
            if ($playlist->image_path) Storage::disk(self::DISK)->delete($playlist->image_path);
            $file = $request->file('image');
            $name = Str::ulid() . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $data['image_path'] = $file->storeAs('playlists/' . now()->format('Y/m'), $name, self::DISK);
        }
        unset($data['image']);

        $playlist->update($data);

        return redirect()->route('media.index')->with('status', 'Playliste opdateret.');
    }

    public function destroyPlaylist(Playlist $playlist): RedirectResponse
    {
        if ($playlist->image_path) Storage::disk(self::DISK)->delete($playlist->image_path);
        $playlist->delete(); // pivot rows cascade; media items themselves are untouched

        return redirect()->route('media.index')->with('status', 'Playliste slettet.');
    }

    /** The chosen playlist from a form's "Tilføj til playliste?" controls, if any. */
    private function resolvePlaylist(Request $request): ?Playlist
    {
        if ($request->input('playlist_id') === 'new') {
            return Playlist::firstOrCreate(['name' => trim($request->input('new_playlist'))]);
        }
        if ($request->filled('playlist_id')) {
            return Playlist::find($request->input('playlist_id'));
        }
        return null;
    }

    public function destroy(Request $request, MediaItem $mediaItem): RedirectResponse
    {
        $mediaItem->deleteFiles();
        $mediaItem->delete();

        return redirect()->route('media.index')->with('status', 'Medie slettet.');
    }
}
