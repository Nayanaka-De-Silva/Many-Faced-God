<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Npc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FolderController extends Controller
{
    /**
     * Display a listing of folders.
     */
    public function index(): View
    {
        // Load all folders in one flat query, then wire children in memory so
        // query count is O(1) regardless of tree depth (no recursive eager-load).
        $allFolders = Folder::withCount(['actualNpcs', 'templates'])
            ->orderBy('name')
            ->get();

        $childrenByParent = $allFolders->groupBy('parent_id');

        foreach ($allFolders as $folder) {
            $folder->setRelation('allChildrenWithCounts', $childrenByParent->get($folder->id) ?? collect());
        }

        $folders = $allFolders->whereNull('parent_id')->values();
        $rootNpcs = Npc::whereNull('folder_id')->npcs()->get();

        return view('folders.index', compact('folders', 'rootNpcs'));
    }

    /**
     * Show the form for creating a new folder.
     */
    public function create(Request $request): View
    {
        $parentFolders = Folder::treeOptions();
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
        $folder->load([
            'parent',
            'actualNpcs',
            'templates',
            'children' => fn ($query) => $query->withCount(['actualNpcs', 'templates']),
        ])->loadCount(['actualNpcs', 'templates']);

        return view('folders.show', compact('folder'));
    }

    /**
     * Show the form for editing the specified folder.
     */
    public function edit(Folder $folder): View
    {
        $parentFolders = Folder::treeOptions(excludeFolderId: $folder->id);

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

                    $descendantIds = Folder::descendantIds($folder->id);
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

}
