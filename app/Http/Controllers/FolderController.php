<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Npc;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class FolderController extends Controller
{
    /**
     * Display a listing of folders.
     */
    public function index(): View
    {
        $folders = Folder::with(['children', 'npcs'])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        $rootNpcs = Npc::whereNull('folder_id')->npcs()->get();

        return view('folders.index', compact('folders', 'rootNpcs'));
    }

    /**
     * Show the form for creating a new folder.
     */
    public function create(Request $request): View
    {
        $parentFolders = Folder::orderBy('name')->get();
        $parentId = $request->get('parent_id');
        
        return view('folders.create', compact('parentFolders', 'parentId'));
    }

    /**
     * Store a newly created folder.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:folders,id',
        ]);

        $folder = Folder::create($validated);

        return redirect()
            ->route('folders.show', $folder)
            ->with('success', 'Folder created successfully!');
    }

    /**
     * Display the specified folder.
     */
    public function show(Folder $folder): View
    {
        $folder->load(['children', 'npcs', 'parent']);
        
        return view('folders.show', compact('folder'));
    }

    /**
     * Show the form for editing the specified folder.
     */
    public function edit(Folder $folder): View
    {
        $parentFolders = Folder::where('id', '!=', $folder->id)
            ->orderBy('name')
            ->get();
        
        return view('folders.edit', compact('folder', 'parentFolders'));
    }

    /**
     * Update the specified folder.
     */
    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => [
                'nullable',
                'exists:folders,id',
                function ($attribute, $value, $fail) use ($folder) {
                    // Prevent setting parent to self or descendants
                    if ($value == $folder->id) {
                        $fail('A folder cannot be its own parent.');
                    }
                    
                    $descendantIds = $this->getDescendantIds($folder);
                    if (in_array($value, $descendantIds)) {
                        $fail('A folder cannot be moved into its own descendant.');
                    }
                },
            ],
        ]);

        $folder->update($validated);

        return redirect()
            ->route('folders.show', $folder)
            ->with('success', 'Folder updated successfully!');
    }

    /**
     * Remove the specified folder.
     */
    public function destroy(Folder $folder): RedirectResponse
    {
        // Move NPCs to parent folder before deletion
        if ($folder->parent_id) {
            $folder->npcs()->update(['folder_id' => $folder->parent_id]);
        } else {
            $folder->npcs()->update(['folder_id' => null]);
        }

        $folder->delete();

        return redirect()
            ->route('folders.index')
            ->with('success', 'Folder deleted successfully!');
    }

    /**
     * Get all descendant folder IDs.
     */
    private function getDescendantIds(Folder $folder): array
    {
        $ids = [];
        
        foreach ($folder->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        
        return $ids;
    }
}
