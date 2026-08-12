<?php
declare(strict_types=1);

// login.php - Subscription & Renewal Management Login Page
require_once __DIR__ . '/includes/bootstrap.php';

// Already logged in redirect to dashboard
if (Auth::check()) {
    header("Location: " . APP_URL . "/dashboard");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email address and password are required.';
    } else {
        if (Auth::login($email, $password)) {
            header("Location: " . APP_URL . "/dashboard");
            exit;
        } else {
            $error = 'Invalid email address or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - <?= APP_NAME ?></title>
    <!-- Favicon / Tab Logo -->
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="<?= APP_URL ?>/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-color: #12b1b0;
            --primary-hover: #0f9d9c;
            --primary-light: #e0f2f2;
            --border-color: #e5e7eb;
            --hover-bg: #f9fafb;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --body-bg: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            --card-bg: #ffffff;
            --input-bg: #ffffff;
            --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--body-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            min-height: 100vh;
        }

        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 1.25rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
            max-width: 960px;
            width: 100%;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
        }

        .login-main {
            padding: 3rem 2.75rem;
        }

        .login-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2.5rem;
        }

        .login-logo img {
            height: 44px;
            width: auto;
            object-fit: contain;
        }

        .login-logo-text {
            font-weight: 800;
            letter-spacing: -0.04em;
            font-size: 2rem;
            color: var(--primary-color);
            line-height: 1;
        }

        .login-title {
            font-weight: 700;
            font-size: 1.875rem;
            letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .error-alert {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .login-form-group {
            margin-bottom: 1.25rem;
        }

        .login-label-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.35rem;
            align-items: center;
        }

        .login-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .login-link {
            font-size: 0.8125rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        .login-input {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .login-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(18, 177, 176, 0.12);
        }

        .login-checkbox-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .login-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .login-button {
            width: 100%;
            border-radius: 0.75rem;
            border: none;
            padding: 0.875rem 1.25rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: white;
            cursor: pointer;
            background: var(--primary-color);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 20px rgba(18, 177, 176, 0.3);
            background: var(--primary-hover);
        }

        .login-side {
            padding: 3rem 2.75rem;
            border-left: 1px solid var(--border-color);
            background: 
                radial-gradient(circle at top left, var(--primary-light), transparent 70%), 
                radial-gradient(circle at bottom right, #e2e8f0, transparent 70%),
                #fafaf7;
            position: relative;
        }

        .login-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: rgba(15,23,42,0.06);
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .login-side-title {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 0.75rem;
        }

        .login-side-text {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            margin-bottom: 1.75rem;
            font-weight: 400;
            line-height: 1.5;
        }

        .login-side-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .login-side-metric {
            padding: 0.85rem 0.9rem;
            border-radius: 0.9rem;
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(148,163,184,0.3);
            backdrop-filter: blur(8px);
        }

        .login-side-metric-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.1rem;
            font-weight: 600;
        }

        .login-side-metric-value {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .login-side-badge {
            position: absolute;
            bottom: 2.25rem;
            right: 2.5rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            background: rgba(15,23,42,0.04);
            font-weight: 500;
        }

        @media (max-width: 900px) {
            .login-card {
                grid-template-columns: 1fr;
            }
            .login-side {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-main">
                <div class="login-logo">
                    <img src="<?= APP_URL ?>/images/logo.png" alt="Renewly Logo">
                    <span class="login-logo-text">Renewly</span>
                </div>
                <h1 class="login-title">Sign in to your account</h1>
                <p class="login-subtitle">Use your credentials to access the dashboard.</p>

                <?php if (!empty($error)): ?>
                <div class="error-alert">
                    <i data-lucide="alert-circle" style="width: 18px; height: 18px; stroke-width: 2;"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="login-form-group">
                        <div class="login-label-row">
                            <label class="login-label" for="email">Username or Email Address</label>
                        </div>
                        <input type="text" class="login-input" id="email" name="email" placeholder="admin@renewly.com" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="login-form-group">
                        <div class="login-label-row">
                            <label class="login-label" for="password">Password</label>
                        </div>
                        <input id="password" type="password" class="login-input" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="login-checkbox-row">
                        <label class="login-checkbox">
                            <input type="checkbox" name="remember" style="accent-color: var(--primary-color);">
                            <span>Remember me</span>
                        </label>
                    </div>

                    <button type="submit" class="login-button">
                        <i data-lucide="log-in" style="width: 18px; height: 18px; stroke-width: 2;"></i>
                        Sign in
                    </button>
                </form>
            </div>
            <div class="login-side">
                <div class="login-tag">
                    <i data-lucide="shield-check" style="width: 14px; height: 14px;"></i>
                    Enterprise-ready security
                </div>
                <h2 class="login-side-title">Subscription & Renewal Management</h2>
                <p class="login-side-text">
                    Proactively manage client software subscriptions, proration calculations, payment schedules, and 30–60 day advance renewal tracking.
                </p>
                <div class="login-side-metrics">
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Subscriptions</div>
                        <div class="login-side-metric-value">500+</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Renewals</div>
                        <div class="login-side-metric-value">100%</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Notice</div>
                        <div class="login-side-metric-value">60 Days</div>
                    </div>
                    <div class="login-side-metric">
                        <div class="login-side-metric-label">Satisfaction</div>
                        <div class="login-side-metric-value">4.9/5</div>
                    </div>
                </div>
                <div class="login-side-badge">
                    <span>Subscription Operations & Auditing</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>