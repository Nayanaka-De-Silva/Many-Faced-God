<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Npc;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): View
    {
        $recentNpcs = Npc::npcs()
            ->with(['folder'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $npcCount = Npc::npcs()->count();
        $templateCount = Npc::templates()->count();
        $folderCount = Folder::count();

        return view('dashboard', compact('recentNpcs', 'npcCount', 'templateCount', 'folderCount'));
    }
}
