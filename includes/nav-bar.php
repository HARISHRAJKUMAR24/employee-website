<!-- header.php - Updated with Auth Links -->
<header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
    <div class="mx-auto max-w-7xl px-5 lg:px-8">
        <div class="flex h-20 items-center justify-between">

            <!-- Logo -->
            <a href="index.php" class="flex items-center gap-2">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-200">
                    <i data-lucide="briefcase-business" class="h-5 w-5"></i>
                </div>

                <div>
                    <div class="text-xl font-extrabold tracking-tight">
                        Job<span class="text-indigo-600">Hub</span>
                    </div>
                    <div class="hidden text-[9px] font-semibold uppercase tracking-widest text-slate-400 sm:block">
                        Career starts here
                    </div>
                </div>
            </a>

            <!-- Desktop Navigation -->
            <nav class="hidden items-center gap-8 lg:flex">
                <a href="index.php#jobs" class="text-sm font-semibold text-slate-700 transition hover:text-indigo-600">
                    Find Jobs
                </a>
                <a href="index.php#companies" class="text-sm font-semibold text-slate-700 transition hover:text-indigo-600">
                    Companies
                </a>
                <a href="index.php#categories" class="text-sm font-semibold text-slate-700 transition hover:text-indigo-600">
                    Categories
                </a>
                <a href="index.php#career" class="text-sm font-semibold text-slate-700 transition hover:text-indigo-600">
                    Career Advice
                </a>
            </nav>

            <!-- Actions -->
            <div class="hidden items-center gap-3 sm:flex">
                <?php if (isset($_SESSION['employee_id']) && !empty($_SESSION['employee_id'])): ?>
                    <!-- Logged In User -->
                    <div class="relative group">
                        <button class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                            <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                                <?php echo strtoupper(substr($_SESSION['employee_name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars($_SESSION['employee_name'] ?? 'User'); ?></span>
                            <i data-lucide="chevron-down" class="h-4 w-4"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl border border-slate-200 shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                            <div class="p-2 space-y-1">
                                <a href="profile.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                    <i data-lucide="user" class="h-4 w-4"></i> My Profile
                                </a>

                                <hr class="my-1 border-slate-100">
                                <a href="logout.php" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i data-lucide="log-out" class="h-4 w-4"></i> Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Not Logged In -->
                    <a href="signin.php" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">
                        Sign In
                    </a>
                    <a href="signup.php" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700">
                        Sign Up
                    </a>
                <?php endif; ?>
               
            </div>

            <!-- Mobile Button -->
            <button id="menuBtn" class="rounded-xl border border-slate-200 p-2.5 lg:hidden">
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div id="mobileMenu" class="mobile-menu border-t border-slate-100 py-5 lg:hidden">
            <nav class="flex flex-col gap-1">
                <a href="index.php#jobs" class="rounded-xl px-4 py-3 text-sm font-semibold hover:bg-slate-50">
                    Find Jobs
                </a>
                <a href="index.php#companies" class="rounded-xl px-4 py-3 text-sm font-semibold hover:bg-slate-50">
                    Companies
                </a>
                <a href="index.php#categories" class="rounded-xl px-4 py-3 text-sm font-semibold hover:bg-slate-50">
                    Categories
                </a>
                <a href="subscription-plans.php" class="text-sm font-semibold text-slate-700 transition hover:text-indigo-600">
                   
                    Subscribe
                </a>
                <a href="index.php#career" class="rounded-xl px-4 py-3 text-sm font-semibold hover:bg-slate-50">
                    Career Advice
                </a>

                <div class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                    <?php if (isset($_SESSION['employee_id']) && !empty($_SESSION['employee_id'])): ?>
                        <a href="profile.php" class="rounded-xl border border-slate-200 py-3 text-sm font-semibold text-center">
                            My Profile
                        </a>
                        <a href="logout.php" class="rounded-xl bg-red-600 py-3 text-sm font-semibold text-white text-center">
                            Sign Out
                        </a>
                    <?php else: ?>
                        <a href="signin.php" class="rounded-xl border border-slate-200 py-3 text-sm font-semibold text-center">
                            Sign In
                        </a>
                        <a href="signup.php" class="rounded-xl bg-indigo-600 py-3 text-sm font-semibold text-white text-center">
                            Sign Up
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </div>
</header>