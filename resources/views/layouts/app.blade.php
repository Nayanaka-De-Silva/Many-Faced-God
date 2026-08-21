<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Many Faced God') }} - @yield('title', 'Dashboard')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --dnd-red: #8B0000;
            --dnd-gold: #C9A227;
            --dnd-parchment: #F5E6D3;
            --dnd-dark: #1a1a1a;

            /* Sidebar geometry. Expanded is the default; the collapsed rail
               values below override this switch, never these properties directly. */
            --sidebar-width: 250px;
            --sidebar-link-justify: flex-start;
            --sidebar-link-padding-x: 1rem;
            --sidebar-icon-gap: 0.5rem;
            --sb-when-expanded: block;
            --sb-when-collapsed: none;
        }

        /*
         * Collapsed rail values. These appear TWICE and must be kept in sync:
         *   1. as the automatic default in the md band (768-991.98px)
         *   2. as the explicit user override (html.sb-collapsed), at any width >= 768px
         * Both selectors are specificity (0,1,1) and beat :root's (0,1,0);
         * media queries add no specificity, so an explicit override always wins.
         */
        @media (min-width: 768px) and (max-width: 991.98px) {
            html:not(.sb-expanded) {
                --sidebar-width: 72px;
                --sidebar-link-justify: center;
                --sidebar-link-padding-x: 0;
                --sidebar-icon-gap: 0;
                --sb-when-expanded: none;
                --sb-when-collapsed: block;
            }
        }

        html.sb-collapsed {
            --sidebar-width: 72px;
            --sidebar-link-justify: center;
            --sidebar-link-padding-x: 0;
            --sidebar-icon-gap: 0;
            --sb-when-expanded: none;
            --sb-when-collapsed: block;
        }

        [data-bs-theme="dark"] {
            --bs-body-bg: #0d1117;
            --bs-body-color: #e0e0e0;
            --bs-card-bg: #161b22;
            --bs-card-border-color: #30363d;
            --bs-form-control-bg: #1c212a;
            --bs-form-control-color: #e0e0e0;
            --bs-form-control-border-color: #30363d;
            --bs-input-focus-bg: #1c212a;
            --bs-input-focus-color: #e0e0e0;
            --bs-input-focus-border-color: var(--dnd-gold);
            --bs-input-focus-box-shadow: 0 0 0 0.25rem rgba(201, 162, 39, 0.25);
            --bs-btn-color: #e0e0e0;
            --bs-btn-bg: #30363d;
            --bs-btn-border-color: #30363d;
            --bs-btn-hover-bg: #484f58;
            --bs-btn-hover-border-color: #30363d;
            --bs-btn-active-bg: #30363d;
            --bs-btn-active-border-color: #30363d;
            --bs-list-group-bg: #161b22;
            --bs-list-group-border-color: #30363d;
            --bs-list-group-color: #e0e0e0;
            --bs-table-bg: #161b22;
            --bs-table-color: #e0e0e0;
            --bs-table-border-color: #30363d;
            --bs-modal-bg: #161b22;
            --bs-modal-border-color: #30363d;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #0d1117;
            color: #e0e0e0;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--dnd-dark) 0%, #2d2d2d 100%);
            padding: 1rem;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            transition: width 0.2s;
        }

        /* Collapse toggle. Hidden until JS confirms it can actually do something. */
        .sidebar-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.375rem 0;
            margin-bottom: 0.25rem;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 0.375rem;
            color: var(--dnd-gold);
            line-height: 1;
            transition: all 0.2s;
        }

        html.has-js .sidebar-toggle {
            display: flex;
        }

        .sidebar-toggle:hover {
            background-color: var(--dnd-red);
            color: #fff;
        }

        .sidebar-toggle:focus-visible {
            outline: 2px solid var(--dnd-gold);
            outline-offset: 2px;
        }

        .sidebar-toggle .sb-icon-expanded { display: var(--sb-when-expanded); }
        .sidebar-toggle .sb-icon-collapsed { display: var(--sb-when-collapsed); }

        .sidebar .brand {
            color: var(--dnd-gold);
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            padding: 1rem 0;
            border-bottom: 2px solid var(--dnd-gold);
            margin-bottom: 1rem;
        }

        .sidebar .brand .brand-text {
            display: var(--sb-when-expanded);
        }

        .sidebar .nav-link {
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: var(--sidebar-link-justify);
            padding: 0.75rem var(--sidebar-link-padding-x);
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            transition: all 0.2s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--dnd-red);
            color: #fff;
        }

        .sidebar .nav-link:focus-visible {
            outline: 2px solid var(--dnd-gold);
            outline-offset: -2px;
        }

        .sidebar .nav-link i {
            flex: 0 0 auto;
            margin-right: var(--sidebar-icon-gap);
        }

        .sidebar .nav-link .nav-label {
            display: var(--sb-when-expanded);
        }

        .sidebar .sidebar-footer {
            display: var(--sb-when-expanded);
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.2s;
        }

        /* NPC Card Styles */
        .npc-card {
            background: linear-gradient(to bottom, #161b22, #0d1117);
            border: 3px solid var(--dnd-red);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            color: #e0e0e0;
        }

        .npc-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(139, 0, 0, 0.4);
        }

        .npc-card .card-header {
            background: var(--dnd-red);
            color: var(--dnd-gold);
            font-weight: bold;
            border-bottom: 2px solid var(--dnd-gold);
            border-radius: 7px 7px 0 0;
        }

        .npc-card .card-body {
            font-size: 0.9rem;
        }

        .npc-card .stat-block {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #30363d;
            border-bottom: 1px solid #30363d;
            padding: 0.5rem 0;
            margin: 0.5rem 0;
        }

        .npc-card .stat {
            text-align: center;
            flex: 1;
        }

        .npc-card .stat-name {
            font-weight: bold;
            font-size: 0.75rem;
            color: var(--dnd-gold);
        }

        .npc-card .stat-value {
            font-size: 1rem;
            color: #e0e0e0;
        }

        .npc-card .stat-modifier {
            font-size: 0.8rem;
            color: #888;
        }

        /* Template Card Styles */
        /* Overrides the red border/header for NPC cards that represent templates */
        .npc-card.template-card {
            border-color: rgba(var(--bs-secondary-rgb), 1);
        }

        .npc-card.template-card:hover {
            box-shadow: 0 8px 15px rgba(108, 117, 125, 0.4);
        }

        .npc-card.template-card .card-header {
            background: rgba(var(--bs-secondary-rgb), 1);
            color: #fff;
            border-bottom-color: #adb5bd;
        }

        /* Gray card header for edit-form section cards when editing a template */
        .template-mode .card-header {
            background-color: rgba(var(--bs-secondary-rgb), 1);
            color: #fff;
        }

        .npc-notes-preview {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
            overflow: hidden;
        }

        .npc-notes-content {
            white-space: pre-line;
        }

        /* Note cards (view and edit mode) */
        .note-card {
            border-left: 3px solid var(--dnd-red);
            padding-left: 0.5rem;
        }

        .note-card-title {
            color: var(--dnd-gold);
            font-weight: 600;
            text-decoration: none;
        }

        .note-card-handle {
            cursor: grab;
        }

        .note-card-row.dragging {
            opacity: .5;
        }

        /* Divider */
        .dnd-divider {
            border: none;
            height: 2px;
            background: linear-gradient(to right, transparent, var(--dnd-red), transparent);
            margin: 1rem 0;
        }

        /* Create Button */
        .create-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--dnd-red);
            color: var(--dnd-gold);
            border: 3px solid var(--dnd-gold);
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(139, 0, 0, 0.4);
            z-index: 1000;
            transition: all 0.2s;
        }

        .create-btn:hover {
            transform: scale(1.1);
            background: #a00000;
            color: #fff;
            border-color: #fff;
        }

        /* Badges and buttons */
        .badge {
            background-color: #30363d;
            color: #e0e0e0;
        }

        .badge-cr {
            background-color: var(--dnd-red);
            color: var(--dnd-gold);
        }

        .btn-primary {
            background-color: var(--dnd-red);
            border-color: var(--dnd-red);
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #a00000;
            border-color: #a00000;
        }

        .btn-primary:focus {
            background-color: var(--dnd-red);
            box-shadow: 0 0 0 0.25rem rgba(139, 0, 0, 0.25);
        }

        .btn-secondary {
            background-color: #30363d;
            border-color: #30363d;
            color: #e0e0e0;
        }

        .btn-secondary:hover {
            background-color: #484f58;
            border-color: #484f58;
        }

        .btn-outline-primary {
            color: var(--dnd-red);
            border-color: var(--dnd-red);
        }

        .btn-outline-primary:hover {
            background-color: var(--dnd-red);
            border-color: var(--dnd-red);
            color: #fff;
        }

        /* Modal styling */
        .modal-content {
            background-color: #161b22;
            border-color: #30363d;
            color: #e0e0e0;
        }

        .modal-header {
            border-bottom-color: #30363d;
        }

        .modal-footer {
            border-top-color: #30363d;
        }

        /* Tables */
        .table {
            color: #e0e0e0;
            border-color: #30363d;
        }

        .table-dark {
            background-color: #161b22;
            color: #e0e0e0;
        }

        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #0d1117;
        }

        /* Pagination */
        .pagination .page-link {
            background-color: #1c212a;
            border-color: #30363d;
            color: var(--dnd-gold);
        }

        .pagination .page-link:hover {
            background-color: #30363d;
            border-color: #30363d;
            color: #fff;
        }

        .pagination .page-link.active {
            background-color: var(--dnd-red);
            border-color: var(--dnd-red);
        }

        /* Breadcrumb */
        .breadcrumb {
            background-color: #161b22;
            border: 1px solid #30363d;
            border-radius: 0.375rem;
        }

        .breadcrumb-item {
            color: #e0e0e0;
        }

        .breadcrumb-item.active {
            color: var(--dnd-gold);
        }

        .breadcrumb-item a {
            color: var(--dnd-gold);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            text-decoration: underline;
        }

        /* List group */
        .list-group-item {
            background-color: #161b22;
            border-color: #30363d;
            color: #e0e0e0;
        }

        .list-group-item.active {
            background-color: var(--dnd-red);
            border-color: var(--dnd-red);
        }

        /* Text utilities */
        .text-muted {
            color: #888 !important;
        }

        .text-secondary {
            color: #888 !important;
        }

        /* Dividers */
        hr {
            border-color: #30363d;
        }

        /* Forms */
        .form-label {
            font-weight: 600;
            color: #e0e0e0;
        }

        .form-control,
        .form-select {
            background-color: #1c212a;
            color: #e0e0e0;
            border-color: #30363d;
        }

        .form-control:focus,
        .form-select:focus {
            background-color: #1c212a;
            color: #e0e0e0;
            border-color: var(--dnd-gold);
            box-shadow: 0 0 0 0.25rem rgba(201, 162, 39, 0.25);
        }

        .form-control::placeholder {
            color: #666;
        }

        textarea.form-control {
            background-color: #1c212a;
            color: #e0e0e0;
            border-color: #30363d;
        }

        /* Alert styling */
        .alert-success {
            background-color: #0f3918;
            border-color: var(--dnd-gold);
            color: #d4edda;
        }

        .alert-danger {
            background-color: #3d0f0f;
            border-color: var(--dnd-red);
            color: #f8d7da;
        }

        .btn-close {
            filter: invert(1);
        }

        /* Folder tree indentation */
        .folder-tree-children { padding-left: 1rem; border-left: 1px solid var(--bs-border-color); }
        @media (min-width: 768px) { .folder-tree-children { padding-left: 1.5rem; } }

        /* Responsive: below md the sidebar is hidden and the mobile navbar takes over. */
        @media (max-width: 767.98px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .sidebar,
            .main-content,
            .sidebar .nav-link,
            .sidebar-toggle {
                transition: none;
            }
        }
    </style>

    <script>
        (function () {
            var root = document.documentElement;
            root.classList.add('has-js');
            try {
                var state = localStorage.getItem('mfg.sidebar');
                if (state === 'collapsed' || state === 'expanded') {
                    root.classList.add('sb-' + state);
                }
            } catch (e) { /* storage blocked; fall back to the breakpoint default */ }
        })();
    </script>

    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar d-none d-md-block" id="sidebarNav">
        <button type="button"
                id="sidebarToggle"
                class="sidebar-toggle"
                aria-controls="sidebarNav"
                aria-expanded="true"
                aria-label="Toggle sidebar"
                title="Toggle sidebar">
            <i class="bi bi-chevron-double-left sb-icon-expanded" aria-hidden="true"></i>
            <i class="bi bi-chevron-double-right sb-icon-collapsed" aria-hidden="true"></i>
        </button>

        <div class="brand">
            <i class="bi bi-masks" aria-hidden="true"></i>
            <div class="brand-text">Many Faced God</div>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" title="Dashboard" aria-label="Dashboard">
                    <i class="bi bi-house-door" aria-hidden="true"></i><span class="nav-label">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('npcs.*') ? 'active' : '' }}" href="{{ route('npcs.index') }}" title="NPCs" aria-label="NPCs">
                    <i class="bi bi-people" aria-hidden="true"></i><span class="nav-label">NPCs</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('templates.*') ? 'active' : '' }}" href="{{ route('templates.index') }}" title="Templates" aria-label="Templates">
                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i><span class="nav-label">Templates</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('folders.*') ? 'active' : '' }}" href="{{ route('folders.index') }}" title="Folders" aria-label="Folders">
                    <i class="bi bi-folder" aria-hidden="true"></i><span class="nav-label">Folders</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}" title="Settings" aria-label="Settings">
                    <i class="bi bi-gear" aria-hidden="true"></i><span class="nav-label">Settings</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <hr class="dnd-divider">
            <div class="text-muted small text-center">
                D&D 5e (2014)
            </div>
        </div>
    </nav>

    <!-- Mobile Nav -->
    <nav class="navbar navbar-dark d-md-none" style="background: var(--dnd-dark);">
        <div class="container-fluid">
            <a class="navbar-brand text-warning" href="{{ route('dashboard') }}">
                <i class="bi bi-masks"></i> Many Faced God
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mobileNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('npcs.index') }}">NPCs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('templates.index') }}">Templates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('folders.index') }}">Folders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('settings.edit') }}">Settings</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Create NPC Button -->
    <a href="{{ route('npcs.create') }}" class="btn create-btn d-flex align-items-center justify-content-center" title="Create NPC">
        <i class="bi bi-plus-lg"></i>
    </a>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Sidebar collapse toggle -->
    <script>
        (function () {
            var root = document.documentElement;
            var btn = document.getElementById('sidebarToggle');
            if (!btn) return;

            var KEY = 'mfg.sidebar';
            var wide = window.matchMedia('(min-width: 992px)');

            function isCollapsed() {
                if (root.classList.contains('sb-collapsed')) return true;
                if (root.classList.contains('sb-expanded')) return false;
                return !wide.matches;
            }

            function syncButton() {
                var collapsed = isCollapsed();
                var label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
                btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                btn.setAttribute('aria-label', label);
                btn.setAttribute('title', label);
            }

            btn.addEventListener('click', function () {
                var collapsed = !isCollapsed();
                root.classList.toggle('sb-collapsed', collapsed);
                root.classList.toggle('sb-expanded', !collapsed);
                try {
                    localStorage.setItem(KEY, collapsed ? 'collapsed' : 'expanded');
                } catch (e) { /* storage blocked; state just won't persist across reloads */ }
                syncButton();
            });

            if (wide.addEventListener) {
                wide.addEventListener('change', syncButton);
            }

            syncButton();
        })();
    </script>

    {{-- Body-level home for fixed-position UI (modals, backdrops). Keeping modals out of
         page containers avoids transformed ancestors re-anchoring their fixed positioning. --}}
    @stack('modals')

    @stack('scripts')
</body>
</html>
