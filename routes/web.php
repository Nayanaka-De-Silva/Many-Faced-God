<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NpcController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SpellLibraryProxyController;
use App\Http\Controllers\TemplateController;

// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// NPCs
Route::resource('npcs', NpcController::class);
Route::post('npcs/{npc}/duplicate', [NpcController::class, 'duplicate'])->name('npcs.duplicate');
Route::post('npcs/{npc}/move', [NpcController::class, 'move'])->name('npcs.move');
Route::post('npcs/generate', [NpcController::class, 'generate'])->name('npcs.generate');

// Folders
Route::resource('folders', FolderController::class);

// Templates
Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
Route::get('templates/{template}', [TemplateController::class, 'show'])->name('templates.show');

// Settings
Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('settings/spell-library/test', [SettingsController::class, 'testConnection'])->name('settings.test-spell-library');

// Spell Library Proxy (read-only; browser-facing facade over the library-of-netheril service)
Route::get('api/spell-library/spells', [SpellLibraryProxyController::class, 'index'])->name('spell-library.index');
Route::get('api/spell-library/spells/{id}', [SpellLibraryProxyController::class, 'show'])
    ->where('id', '[A-Za-z0-9\-]+')
    ->name('spell-library.show');
Route::get('api/spell-library/health', [SpellLibraryProxyController::class, 'health'])->name('spell-library.health');
