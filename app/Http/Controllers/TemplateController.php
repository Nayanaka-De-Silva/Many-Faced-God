<?php

namespace App\Http\Controllers;

use App\Models\Npc;
use App\Models\Folder;
use Illuminate\View\View;

class TemplateController extends Controller
{
    /**
     * Display a listing of templates.
     */
    public function index(): View
    {
        $templates = Npc::templates()
            ->with(['folder', 'traits', 'actions'])
            ->orderBy('name')
            ->paginate(25);

        return view('templates.index', compact('templates'));
    }

    /**
     * Display the specified template.
     */
    public function show(Npc $template): View
    {
        if (!$template->is_template) {
            abort(404);
        }

        $template->load(['folder', 'traits', 'actions', 'spellcasting']);
        
        return view('templates.show', compact('template'));
    }
}
