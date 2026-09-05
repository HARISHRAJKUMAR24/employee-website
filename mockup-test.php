<?php
require_once './config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['employee_id']) && !empty($_SESSION['employee_id']);
$user_data = null;
$subscription_plan = null;
$has_reached_limit = false;
$limit_message = '';
$test_limit = 0;
$tests_used = 0;
$can_take_test = false;

if (!$is_logged_in) {
    header('Location: signin.php?redirect=mockup-test.php');
    exit;
}

// Get user data and subscription info
try {
    $user_stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND is_active = 1");
    $user_stmt->execute([$_SESSION['employee_id']]);
    $user_data = $user_stmt->fetch();
    
    if ($user_data && $user_data['subscription_status'] == 'active' && strtotime($user_data['subscription_expiry_date']) > time()) {
        // Get subscription plan details
        $sub_stmt = $pdo->prepare("
            SELECT * FROM employee_subscriptions_plans 
            WHERE id = ? AND is_active = 1
        ");
        $sub_stmt->execute([$user_data['subscription_id']]);
        $subscription_plan = $sub_stmt->fetch();
        
        if ($subscription_plan) {
            // Get mockup tests limit and used
            $test_limit = $subscription_plan['mockup_tests_limit'] ?? 0;
            $tests_used = $user_data['mockup_tests_used'] ?? 0;
            
            // Check if user has remaining tests
            if ($test_limit > 0 && $tests_used >= $test_limit) {
                $has_reached_limit = true;
                $limit_message = 'You have reached your mockup test limit (' . $test_limit . '). Please upgrade your subscription to continue.';
            } else {
                $can_take_test = true;
            }
        }
    } else {
        $has_reached_limit = true;
        $limit_message = 'You need an active subscription to take mockup tests. Please subscribe to continue.';
    }
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Get job ID from URL (optional)
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : null;

// Handle test submission
$test_submitted = false;
$test_result = null;
$current_round = 1;
$max_rounds = 2; // Number of rounds

// Get current round from session or URL
if (isset($_SESSION['test_round'])) {
    $current_round = $_SESSION['test_round'];
}

// Fetch questions for current round
$questions = [];
try {
    $q_stmt = $pdo->prepare("
        SELECT * FROM mockup_test_questions 
        WHERE round = ? AND is_active = 1
        ORDER BY RAND()
        LIMIT 5
    ");
    $q_stmt->execute([$current_round]);
    $questions = $q_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching questions: " . $e->getMessage());
}

// If no questions in database, use default questions
if (empty($questions)) {
    $default_questions = [
        'What is your biggest professional achievement?',
        'How do you handle stress and pressure?',
        'Describe a situation where you showed initiative.',
        'What are your career goals?',
        'Why should we hire you?'
    ];
    foreach ($default_questions as $q) {
        $questions[] = ['id' => 0, 'question' => $q, 'category' => 'General', 'difficulty' => 'medium'];
    }
}

// Handle test submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    if (!$can_take_test) {
        $error_message = $limit_message;
    } else {
        $answers = [];
        $correct_count = 0;
        $total_questions = count($questions);
        
        // Collect answers
        foreach ($questions as $index => $q) {
            $answer_key = 'answer_' . $q['id'];
            $answers[$q['id']] = trim($_POST[$answer_key] ?? '');
            // For AI evaluation, we'll count non-empty answers as correct
            if (!empty($answers[$q['id']]) && strlen($answers[$q['id']]) > 10) {
                $correct_count++;
            }
        }
        
        // Calculate score
        $score_percentage = ($total_questions > 0) ? ($correct_count / $total_questions) * 100 : 0;
        $passed = $score_percentage >= 60; // Pass threshold: 60%
        
        // Save results
        try {
            $insert_stmt = $pdo->prepare("
                INSERT INTO mockup_test_results (
                    employee_id, job_id, company_id, round, 
                    total_questions, correct_answers, wrong_answers, 
                    score_percentage, answers, status, completed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $wrong_answers = $total_questions - $correct_count;
            $status = $passed ? 'completed' : 'failed';
            
            $insert_stmt->execute([
                $_SESSION['employee_id'],
                $job_id,
                $company_id,
                $current_round,
                $total_questions,
                $correct_count,
                $wrong_answers,
                $score_percentage,
                json_encode($answers),
                $status
            ]);
            
            // Increment mockup tests used
            $update_stmt = $pdo->prepare("
                UPDATE employees 
                SET mockup_tests_used = mockup_tests_used + 1 
                WHERE id = ?
            ");
            $update_stmt->execute([$_SESSION['employee_id']]);
            $tests_used++;
            
            $test_submitted = true;
            $test_result = [
                'total' => $total_questions,
                'correct' => $correct_count,
                'wrong' => $wrong_answers,
                'score' => $score_percentage,
                'passed' => $passed,
                'round' => $current_round
            ];
            
            // Check if all rounds are completed
            if ($passed && $current_round < $max_rounds) {
                $_SESSION['test_round'] = $current_round + 1;
                $_SESSION['test_completed_round'] = $current_round;
            } else {
                $_SESSION['test_round'] = 1;
                $_SESSION['test_completed'] = true;
            }
            
        } catch (PDOException $e) {
            error_log("Error saving test results: " . $e->getMessage());
            $error_message = 'Failed to save test results. Please try again.';
        }
    }
}

// Reset test
if (isset($_GET['reset'])) {
    unset($_SESSION['test_round']);
    unset($_SESSION['test_completed']);
    unset($_SESSION['test_completed_round']);
    header('Location: mockup-test.php' . ($job_id ? '?job_id=' . $job_id : ''));
    exit;
}

// Get test history
$test_history = [];
try {
    $history_stmt = $pdo->prepare("
        SELECT * FROM mockup_test_results 
        WHERE employee_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $history_stmt->execute([$_SESSION['employee_id']]);
    $test_history = $history_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching test history: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Mockup Test - JobHub</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        .question-card {
            transition: all 0.3s ease;
        }
        .question-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 10px;
            background: #e5e7eb;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #4f46e5, #818cf8);
            transition: width 0.5s ease;
        }
        
        .ai-thinking {
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            margin: 0 auto;
        }
        .score-pass {
            background: #d1fae5;
            color: #065f46;
            border: 4px solid #10b981;
        }
        .score-fail {
            background: #fecaca;
            color: #991b1b;
            border: 4px solid #ef4444;
        }
        
        .limit-reached-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #f59e0b;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
        }
        
        .test-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e5e7eb;
        }
        
        .round-badge {
            background: #4f46e5;
            color: white;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>

<body>

    <?php include 'includes/nav-bar.php'; ?>

    <main class="py-12">
        <div class="mx-auto max-w-4xl px-5 lg:px-8">

            <!-- Page Header -->
            <div class="text-center mb-8">
                <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">
                    Interview Preparation
                </p>
                <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
                    Mockup Test
                </h1>
                <p class="mt-3 max-w-xl mx-auto text-slate-500">
                    Practice your interview skills with AI-powered questions and get instant feedback.
                </p>
            </div>

            <!-- Show remaining tests info -->
            <?php if ($is_logged_in && $user_data && $subscription_plan): ?>
                <div class="mb-6 flex items-center justify-between flex-wrap gap-2 bg-white rounded-2xl border border-slate-200 p-4">
                    <div class="flex items-center gap-3">
                        <i data-lucide="file-text" class="h-5 w-5 text-indigo-500"></i>
                        <span class="text-sm text-slate-600">
                            <strong>Mockup Tests:</strong> 
                            <?php echo $tests_used; ?> / <?php echo $test_limit; ?>
                        </span>
                    </div>
                    <div>
                        <?php if ($can_take_test): ?>
                            <span class="inline-block bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-semibold">
                                <i data-lucide="check-circle" class="inline h-3 w-3 mr-1"></i>
                                <?php echo $test_limit - $tests_used; ?> tests remaining
                            </span>
                        <?php else: ?>
                            <span class="inline-block bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-semibold">
                                <i data-lucide="alert-circle" class="inline h-3 w-3 mr-1"></i>
                                Limit Reached
                            </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="subscription-plans.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">
                            Upgrade Plan →
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 p-4 text-red-700">
                    <i data-lucide="alert-circle" class="inline h-5 w-5 mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!$can_take_test): ?>
                <!-- Limit Reached -->
                <div class="limit-reached-card">
                    <i data-lucide="alert-triangle" class="mx-auto h-16 w-16 text-amber-500"></i>
                    <h3 class="mt-4 text-xl font-bold text-slate-900">Test Limit Reached</h3>
                    <p class="mt-2 text-slate-600"><?php echo htmlspecialchars($limit_message); ?></p>
                    <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="subscription-plans.php" 
                           class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                            <i data-lucide="crown" class="inline h-4 w-4 mr-2"></i>
                            Upgrade Subscription
                        </a>
                        <a href="jobs.php" 
                           class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Browse Jobs
                        </a>
                    </div>
                    <?php if ($user_data && $subscription_plan): ?>
                        <div class="mt-4 text-sm text-slate-500">
                            Used: <?php echo $tests_used; ?> / <?php echo $test_limit; ?> tests
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($test_submitted && $test_result): ?>
                <!-- Test Results -->
                <div class="test-card">
                    <div class="text-center">
                        <div class="round-badge mb-4">Round <?php echo $test_result['round']; ?> Complete</div>
                        <div class="score-circle <?php echo $test_result['passed'] ? 'score-pass' : 'score-fail'; ?>">
                            <?php echo round($test_result['score']); ?>%
                        </div>
                        <h3 class="mt-4 text-2xl font-extrabold">
                            <?php echo $test_result['passed'] ? '🎉 Great Job!' : '💪 Keep Practicing!'; ?>
                        </h3>
                        <p class="mt-2 text-slate-600">
                            You answered <?php echo $test_result['correct']; ?> out of <?php echo $test_result['total']; ?> questions correctly.
                        </p>
                        <?php if ($test_result['passed']): ?>
                            <div class="mt-4 p-4 bg-emerald-50 rounded-xl">
                                <i data-lucide="check-circle" class="inline h-5 w-5 text-emerald-500 mr-2"></i>
                                <span class="text-emerald-700 font-semibold">You passed this round!</span>
                            </div>
                        <?php else: ?>
                            <div class="mt-4 p-4 bg-amber-50 rounded-xl">
                                <i data-lucide="alert-circle" class="inline h-5 w-5 text-amber-500 mr-2"></i>
                                <span class="text-amber-700 font-semibold">You need 60% to pass. Try again!</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3 justify-center">
                        <?php if ($test_result['passed'] && $current_round <= $max_rounds && !isset($_SESSION['test_completed'])): ?>
                            <a href="mockup-test.php<?php echo $job_id ? '?job_id=' . $job_id : ''; ?>" 
                               class="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                                <i data-lucide="arrow-right" class="inline h-4 w-4 mr-2"></i>
                                Next Round
                            </a>
                        <?php elseif ($test_result['passed'] && $current_round > $max_rounds): ?>
                            <div class="p-4 bg-emerald-100 rounded-xl text-center w-full">
                                <i data-lucide="trophy" class="h-12 w-12 text-emerald-500 mx-auto"></i>
                                <h4 class="mt-2 font-bold text-emerald-700">🎊 You completed all rounds!</h4>
                                <p class="text-sm text-emerald-600">You're well prepared for your interview.</p>
                            </div>
                        <?php endif; ?>
                        <a href="mockup-test.php?reset=1" 
                           class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            <i data-lucide="refresh-cw" class="inline h-4 w-4 mr-2"></i>
                            Start New Test
                        </a>
                        <a href="jobs.php" 
                           class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                            <i data-lucide="briefcase" class="inline h-4 w-4 mr-2"></i>
                            Browse Jobs
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Test Form -->
                <form method="POST" class="space-y-6">
                    <div class="test-card">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <span class="round-badge">Round <?php echo $current_round; ?> of <?php echo $max_rounds; ?></span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-500">
                                <i data-lucide="clock" class="h-4 w-4"></i>
                                <span>No time limit</span>
                            </div>
                        </div>

                        <!-- Progress -->
                        <div class="mb-6">
                            <div class="flex justify-between text-sm text-slate-500 mb-1">
                                <span>Progress</span>
                                <span>Question <?php echo count($questions); ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-bar-fill" style="width: 100%;"></div>
                            </div>
                        </div>

                        <!-- AI Assistant -->
                        <div class="mb-6 p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">
                                    <i data-lucide="bot" class="h-5 w-5"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-indigo-700">AI Interview Assistant</p>
                                    <p class="text-sm text-indigo-600">Answer each question thoughtfully. The AI will evaluate your responses based on clarity, relevance, and depth.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Questions -->
                        <?php foreach ($questions as $index => $q): ?>
                            <div class="question-card rounded-xl border border-slate-200 bg-white p-6 mb-4">
                                <div class="flex items-start gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-600">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <div class="flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                                <?php echo htmlspecialchars($q['category'] ?? 'General'); ?>
                                            </span>
                                            <span class="text-xs text-slate-400">
                                                Difficulty: <?php echo ucfirst($q['difficulty'] ?? 'medium'); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm font-medium text-slate-900">
                                            <?php echo htmlspecialchars($q['question']); ?>
                                        </p>
                                        <textarea 
                                            name="answer_<?php echo $q['id']; ?>" 
                                            rows="4"
                                            class="mt-3 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition"
                                            placeholder="Type your answer here..."
                                            required
                                        ></textarea>
                                        <div class="mt-2 flex justify-between text-xs text-slate-400">
                                            <span>Minimum 10 characters recommended</span>
                                            <span id="charCount_<?php echo $q['id']; ?>">0 characters</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Submit -->
                        <div class="flex flex-col sm:flex-row gap-3 justify-between items-center mt-6 pt-6 border-t border-slate-200">
                            <div class="text-sm text-slate-500">
                                <i data-lucide="info" class="inline h-4 w-4 mr-1"></i>
                                <?php echo count($questions); ?> questions to answer
                            </div>
                            <div class="flex gap-3">
                                <a href="mockup-test.php?reset=1" 
                                   class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                    Reset
                                </a>
                                <button type="submit" name="submit_test" 
                                        class="rounded-xl bg-indigo-600 px-8 py-3 text-sm font-semibold text-white hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">
                                    <i data-lucide="send" class="inline h-4 w-4 mr-2"></i>
                                    Submit Answers
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Test History -->
            <?php if (!empty($test_history)): ?>
                <div class="mt-12">
                    <h2 class="text-xl font-extrabold text-slate-900 mb-4">Test History</h2>
                    <div class="space-y-3">
                        <?php foreach ($test_history as $history): ?>
                            <div class="flex items-center justify-between p-4 bg-white rounded-xl border border-slate-200">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-slate-900">
                                            Round <?php echo $history['round']; ?>
                                        </span>
                                        <span class="text-xs text-slate-500">
                                            <?php echo date('M d, Y', strtotime($history['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        Score: <?php echo round($history['score_percentage']); ?>% 
                                        (<?php echo $history['correct_answers']; ?>/<?php echo $history['total_questions']; ?>)
                                    </div>
                                </div>
                                <div>
                                    <?php if ($history['status'] == 'completed'): ?>
                                        <span class="inline-block bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-semibold">
                                            ✅ Passed
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-block bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-semibold">
                                            ❌ Failed
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        lucide.createIcons();
        
        // Character counter for each textarea
        document.querySelectorAll('textarea[name^="answer_"]').forEach(textarea => {
            textarea.addEventListener('input', function() {
                const charCount = this.value.length;
                const id = this.name.replace('answer_', '');
                const counter = document.getElementById('charCount_' + id);
                if (counter) {
                    counter.textContent = charCount + ' characters';
                }
            });
        });
        
        // Auto-resize textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    </script>

</body>
</html>