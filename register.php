<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Renewly</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .auth-card { width: 100%; max-width: 400px; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .btn-primary { background-color: #0f172a; border-color: #0f172a; }
        .btn-primary:hover { background-color: #1e293b; border-color: #1e293b; }
    </style>
</head>
<body>
    <div class="auth-card p-4 p-md-5">
        <div class="text-center mb-4">
            <h1 class="h4 fw-bold mb-1">Create an account</h1>
            <p class="text-muted small">Enter your information to get started</p>
        </div>
        
        <form action="../admin/index.php" method="GET">
            <div class="mb-3">
                <label class="form-label small fw-medium text-muted">Full Name</label>
                <input type="text" class="form-control" placeholder="John Doe" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-medium text-muted">Company Name</label>
                <input type="text" class="form-control" placeholder="Acme Inc." required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-medium text-muted">Email</label>
                <input type="email" class="form-control" placeholder="name@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-medium text-muted">Password</label>
                <input type="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-medium">Create account</button>
        </form>
        
        <div class="text-center mt-4">
            <p class="small text-muted mb-0">Already have an account? <a href="login.php" class="fw-medium text-dark text-decoration-none">Sign in</a></p>
        </div>
    </div>
</body>
</html>
