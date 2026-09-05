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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');

    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if email already exists
            $check_stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
            $check_stmt->execute([$email]);
            if ($check_stmt->fetch()) {
                $error = 'This email is already registered. Please sign in.';
            } else {
                // Generate employee ID
                $employee_id = 'EMP-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new employee
                $insert_stmt = $pdo->prepare("
                    INSERT INTO employees (employee_id, full_name, email, password, phone, location, is_verified, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1, 1)
                ");
                $insert_stmt->execute([$employee_id, $full_name, $email, $hashed_password, $phone, $location]);
                
                $success = 'Account created successfully! You can now sign in.';
                
                // Auto login after signup
                $_SESSION['employee_id'] = $pdo->lastInsertId();
                $_SESSION['employee_name'] = $full_name;
                $_SESSION['employee_email'] = $email;
                
                // Redirect to home or dashboard
                header('Location: index.php?welcome=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Signup error: " . $e->getMessage());
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
    <title>Sign Up - JobHub</title>
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
            z-index: 2;
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
        .password-input-wrapper input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
                <h2 class="mt-4 text-2xl font-extrabold text-slate-900">Create an account</h2>
                <p class="mt-2 text-sm text-slate-500">Start your journey to finding the perfect job</p>
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

            <!-- Sign Up Form -->
            <form method="POST" class="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name" required
                               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                               placeholder="John Doe">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required
                               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                               placeholder="john@example.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Phone Number</label>
                        <input type="tel" name="phone"
                               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                               placeholder="+1 234 567 890">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Location</label>
                        <input type="text" name="location"
                               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                               placeholder="New York, NY">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Password <span class="text-red-500">*</span></label>
                        <div class="password-input-wrapper mt-1">
                            <input type="password" name="password" id="password" required minlength="6"
                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                                   placeholder="Min 6 characters">
                            <button type="button" class="password-toggle" onclick="togglePassword('password', 'passwordEyeIcon')">
                                <i data-lucide="eye" id="passwordEyeIcon" class="h-5 w-5"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Confirm Password <span class="text-red-500">*</span></label>
                        <div class="password-input-wrapper mt-1">
                            <input type="password" name="confirm_password" id="confirm_password" required
                                   class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                                   placeholder="Confirm your password">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'confirmEyeIcon')">
                                <i data-lucide="eye" id="confirmEyeIcon" class="h-5 w-5"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" 
                        class="mt-6 w-full rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-indigo-700 shadow-lg shadow-indigo-200">
                    Create Account
                </button>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Already have an account? 
                    <a href="signin.php" class="font-semibold text-indigo-600 hover:text-indigo-700">Sign In</a>
                </p>
            </form>
        </div>
    </main>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Password visibility toggle function
        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const eyeIcon = document.getElementById(iconId);
            
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

        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const confirmField = this;
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    confirmField.classList.remove('border-red-500');
                    confirmField.classList.add('border-emerald-500', 'ring-2', 'ring-emerald-200');
                } else {
                    confirmField.classList.remove('border-emerald-500', 'ring-2', 'ring-emerald-200');
                    confirmField.classList.add('border-red-500', 'ring-2', 'ring-red-200');
                }
            } else {
                confirmField.classList.remove('border-red-500', 'border-emerald-500', 'ring-2', 'ring-red-200', 'ring-emerald-200');
            }
        });

        // Password strength indicator (optional)
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('passwordStrength');
            
            if (!strengthIndicator) {
                // Create strength indicator if it doesn't exist
                const indicator = document.createElement('div');
                indicator.id = 'passwordStrength';
                indicator.className = 'mt-1 text-xs';
                this.parentNode.appendChild(indicator);
            }
            
            const indicator = document.getElementById('passwordStrength');
            if (password.length === 0) {
                indicator.textContent = '';
                indicator.className = 'mt-1 text-xs';
                return;
            }
            
            let strength = 'Weak';
            let color = 'text-red-500';
            
            if (password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password) && /[^A-Za-z0-9]/.test(password)) {
                strength = 'Strong';
                color = 'text-emerald-500';
            } else if (password.length >= 6 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
                strength = 'Medium';
                color = 'text-amber-500';
            }
            
            indicator.textContent = `Password Strength: ${strength}`;
            indicator.className = `mt-1 text-xs ${color}`;
        });

        // Also support Enter key for form submission
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeElement = document.activeElement;
                if (activeElement && activeElement.closest('form')) {
                    activeElement.closest('form').submit();
                }
            }
        });
    </script>
</body>
</html>