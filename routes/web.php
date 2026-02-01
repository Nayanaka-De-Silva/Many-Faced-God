<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\NpcController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

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
