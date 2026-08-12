<?php
// includes/header.php
require_once __DIR__ . '/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renewly Admin Pro</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 64px;
            --footer-height: 56px;
            /* PRIMARY COLOR TEAL */
            --primary-color: #12b1b0; 
            --primary-hover: #0f9d9c;
            --primary-light: #e0f2f2; /* Light teal tint */
            --sidebar-bg: #ffffff;
            --border-color: #e5e7eb;
            --hover-bg: #f9fafb;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --body-bg: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            --card-bg: #ffffff;
            --input-bg: #ffffff;
            --modal-bg: #ffffff;
        }


        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* HEADER */
        .header {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            box-shadow: var(--shadow-sm);
        }


        .logo {
            font-size: 1.25rem; font-weight: 700; color: var(--text-primary);
            text-decoration: none; display: flex; align-items: center; gap: 0.75rem;
        }
        .logo-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d9488 100%);
            color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center;
        }

        .header-actions { margin-left: auto; display: flex; align-items: center; gap: 0.75rem; }
        
        .icon-btn {
            background: var(--hover-bg); border: 1px solid transparent; padding: 0.625rem;
            border-radius: 10px; cursor: pointer; color: var(--text-secondary); transition: var(--transition);
        }
        .icon-btn:hover { background: var(--card-bg); color: var(--primary-color); transform: translateY(-2px); }

        /* MAIN LAYOUT */
        .main-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
        .main-content {
            margin-top: var(--header-height);
            margin-left: var(--sidebar-width);
            padding: 2rem 2rem calc(var(--footer-height) + 2rem);
            transition: var(--transition);
            flex: 1;
        }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed-width); }

        /* Mobile */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
            .sidebar.collapsed ~ .main-content { margin-left: 0; }
        }

        /* UTILS & CARDS (Portions) */
        .card-shadcn, .content-card {
            background: var(--card-bg); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 1.5rem;
            margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);
        }
        
        /* Sidebar styles included in sidebar.php or general scope */
    </style>
    <!-- Include the full CSS dump here or in a separate file? For now, injecting essential layout basics. The full CSS from user is large, assuming user provided style block is globally applied. I will add the rest of the style block in a simplified manner or ensure key classes exist. -->
    <style>
        /* Expanded CSS from user snippet */
        .sidebar {
            position: fixed; top: var(--header-height); left: 0; width: var(--sidebar-width);
            height: calc(100vh - var(--header-height)); background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color); overflow-y: auto; transition: var(--transition); z-index: 1020;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        .sidebar-nav { padding: 1.5rem 0; }
        .nav-link {
            display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1.5rem;
            color: var(--text-secondary); text-decoration: none; font-weight: 500;
            transition: var(--transition); margin: 0 0.75rem; border-radius: 10px;
        }
        .nav-link:hover { background: var(--hover-bg); color: var(--text-primary); transform: translateX(2px); }
        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d9488 100%);
            color: white; box-shadow: 0 4px 12px rgba(18, 177, 176, 0.3);
        }
        .nav-icon { min-width: 20px; display: flex; align-items: center; justify-content: center; }
        .nav-text { flex: 1; white-space: nowrap; transition: var(--transition); }
        .sidebar.collapsed .nav-text { opacity: 0; width: 0; }
        
        .sidebar-toggle { display: none; background: none; border: none; padding: 0.5rem; }
        @media (max-width: 1024px) { 
            .sidebar { transform: translateX(-100%); } 
            .sidebar.show { transform: translateX(0); }
            .sidebar-toggle { display: block; }
        }

        /* Overrides for Buttons to match Teal */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0d9488 100%);
            border-color: transparent; color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-hover) 0%, #0f766e 100%);
        }
        .badge-primary { background: var(--primary-light); color: var(--primary-color); }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <!-- Header -->
        <header class="header">
            <button class="sidebar-toggle me-3" id="sidebarToggle">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">RN</div>
                <span>Renewly</span>
            </a>
            <button class="icon-btn ms-3 d-none d-lg-flex" id="collapseToggle">
                <i data-lucide="panel-left" class="w-5 h-5"></i>
            </button>
            
            <div class="header-actions d-flex align-items-center gap-2">
                <?php $currentUser = Auth::user(); ?>
                <!-- User Menu Pill Dropdown -->
                <div class="dropdown">
                    <a href="#" class="user-menu" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar-placeholder font-bold text-xs">
                            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 2)) ?>
                        </div>
                        <div class="user-info d-none d-md-flex">
                            <span class="user-name"><?= sanitize($currentUser['name']) ?></span>
                            <span class="user-role"><?= sanitize($currentUser['role_name']) ?></span>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-muted ms-1 d-none d-md-inline"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 p-2 mt-2" style="min-width: 220px;">
                        <li class="px-3 py-2 border-bottom mb-1 bg-light rounded-2">
                            <div class="fw-bold text-dark text-sm"><?= sanitize($currentUser['name']) ?></div>
                            <div class="text-xs text-muted"><?= sanitize($currentUser['email']) ?></div>
                            <span class="badge badge-primary mt-1 text-xs"><?= sanitize($currentUser['role_name']) ?></span>
                        </li>
                        <li>
                            <a class="dropdown-item text-danger rounded-2 py-2 text-sm d-flex align-items-center gap-2" href="<?= APP_URL ?>/logout">
                                <i data-lucide="log-out" class="w-4 h-4"></i> Sign Out
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 z-2" id="sidebarOverlay" style="display: none; opacity: 0; transition: opacity 0.3s;"></div>

        <!-- Sidebar Include -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <main class="main-content">
            <?php 
            // Breadcrumb or Page Title Helper could go here
            ?>
