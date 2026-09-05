<?php
require_once './config/config.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['employee_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = isset($_GET['registered']) ? 'Account created successfully! Please sign in.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $employee = $stmt->fetch();

            if ($employee && password_verify($password, $employee['password'])) {
                // Update last login
                $update_stmt = $pdo->prepare("UPDATE employees SET last_login = NOW() WHERE id = ?");
                $update_stmt->execute([$employee['id']]);

                // Set session
                $_SESSION['employee_id'] = $employee['id'];
                $_SESSION['employee_name'] = $employee['full_name'];
                $_SESSION['employee_email'] = $employee['email'];
                $_SESSION['employee_id_code'] = $employee['employee_id'];

                // Redirect to the page they were trying to access or home
                $redirect = $_GET['redirect'] ?? 'index.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log("Signin error: " . $e->getMessage());
            $error = 'Something went wrong. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign In - JobHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        /* Password toggle button styles */
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover {
            color: #1e293b;
        }
        .password-toggle:focus {
            outline: none;
        }
        .password-input-wrapper {
            position: relative;
        }
        .password-input-wrapper input {
            padding-right: 45px;
        }
    </style>
</head>
<body>

    <main class="min-h-screen flex items-center justify-center py-16 px-5">
        <div class="w-full max-w-md">
            <!-- Logo/Brand -->
            <div class="text-center mb-8">
                <div class="flex items-center justify-center gap-2">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-200">
                        <i data-lucide="briefcase-business" class="h-6 w-6"></i>
                    </div>
                    <span class="text-2xl font-extrabold">Job<span class="text-indigo-600">Hub</span></span>
                </div>
                <h2 class="mt-4 text-2xl font-extrabold text-slate-900">Welcome back</h2>
                <p class="mt-2 text-sm text-slate-500">Sign in to your account to continue</p>
            </div>

            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700 flex items-start gap-2">
                    <i data-lucide="alert-circle" class="h-5 w-5 flex-shrink-0 mt-0.5"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700 flex items-start gap-2">
                    <i data-lucide="check-circle" class="h-5 w-5 flex-shrink-0 mt-0.5"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Sign In Form -->
            <form method="POST" class="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Email Address</label>
                        <input type="email" name="email" required
                               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                               placeholder="john@example.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Password</label>
                        <div class="password-input-wrapper mt-1">
                            <input type="password" name="password" id="password" required
                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                                   placeholder="Enter your password">
                            <button type="button" class="password-toggle" id="togglePassword" onclick="togglePasswordVisibility()">
                                <i data-lucide="eye" id="eyeIcon" class="h-5 w-5"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Remember me
                        </label>
                        <a href="forgot-password.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                            Forgot password?
                        </a>
                    </div>
                </div>

                <button type="submit" 
                        class="mt-6 w-full rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-indigo-700 shadow-lg shadow-indigo-200">
                    Sign In
                </button>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Don't have an account? 
                    <a href="signup.php" class="font-semibold text-indigo-600 hover:text-indigo-700">Sign Up</a>
                </p>
            </form>
        </div>
    </main>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Password visibility toggle function
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                // Change icon to eye-off (slash)
                eyeIcon.setAttribute('data-lucide', 'eye-off');
                lucide.createIcons();
            } else {
                passwordInput.type = 'password';
                // Change icon back to eye
                eyeIcon.setAttribute('data-lucide', 'eye');
                lucide.createIcons();
            }
        }

        // Also support Enter key for form submission
        document.getElementById('password').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
    </script>
</body>
</html>