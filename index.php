<?php
require_once './config/config.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>Jobly — Find Your Dream Job</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        inter: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .hero-grid {
            background-image:
                linear-gradient(rgba(99, 102, 241, .07) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99, 102, 241, .07) 1px, transparent 1px);
            background-size: 42px 42px;
        }

        .hero-glow {
            background:
                radial-gradient(circle at 50% 25%, rgba(99, 102, 241, .18), transparent 38%),
                radial-gradient(circle at 85% 50%, rgba(168, 85, 247, .12), transparent 30%);
        }

        .job-card {
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 45px rgba(15, 23, 42, .08);
            border-color: rgb(165 180 252);
        }

        .company-logo {
            transition: transform .2s ease;
        }

        .company-logo:hover {
            transform: translateY(-3px);
        }

        .mobile-menu {
            display: none;
        }

        .mobile-menu.active {
            display: block;
        }
    </style>
</head>

<body class="bg-white text-slate-900">

    <!-- ================= Nav-bar ================= -->

    <?php include 'includes/nav-bar.php'; ?>


    <!-- ================= HERO ================= -->
    <?php include 'includes/hero.php'; ?>


    <!-- ================= CATEGORIES ================= -->
    <?php include 'includes/categories.php'; ?>



    <!-- ================= JOBS ================= -->
    <?php include 'includes/jobs.php'; ?>


    <!-- ================= COMPANIES ================= -->
    <?php include 'includes/companies.php'; ?>


    <!-- ================= JOB SEEKER CTA ================= -->
    <section class="px-5 py-10 lg:px-8">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-3xl bg-indigo-600 px-7 py-14 text-white sm:px-12 lg:px-16 lg:py-20">

            <div class="grid items-center gap-12 lg:grid-cols-2">

                <div>
                    <div class="inline-flex rounded-full bg-white/10 px-4 py-2 text-xs font-bold">
                        FOR JOB SEEKERS
                    </div>

                    <h2 class="mt-6 text-3xl font-extrabold leading-tight sm:text-4xl lg:text-5xl">
                        Your next opportunity
                        could be one click away.
                    </h2>

                    <p class="mt-5 max-w-xl leading-7 text-indigo-100">
                        Create your profile, upload your resume and let top companies
                        discover you.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <button class="rounded-xl bg-white px-6 py-3.5 text-sm font-bold text-indigo-700 transition hover:bg-indigo-50">
                            Create Free Profile
                        </button>

                        <button class="rounded-xl border border-white/30 px-6 py-3.5 text-sm font-bold text-white hover:bg-white/10">
                            Upload Resume
                        </button>
                    </div>
                </div>

                <!-- Visual -->
                <div class="relative hidden lg:block">
                    <div class="absolute -right-10 -top-10 h-72 w-72 rounded-full bg-purple-400/30 blur-3xl"></div>

                    <div class="relative rounded-3xl border border-white/20 bg-white/10 p-5 backdrop-blur">

                        <div class="rounded-2xl bg-white p-5 text-slate-900 shadow-2xl">

                            <div class="flex items-center gap-4">
                                <div class="h-14 w-14 rounded-xl bg-slate-100"></div>

                                <div>
                                    <div class="h-3 w-36 rounded bg-slate-200"></div>
                                    <div class="mt-2 h-2 w-24 rounded bg-slate-100"></div>
                                </div>
                            </div>

                            <div class="mt-7 space-y-3">
                                <div class="h-12 rounded-xl bg-indigo-50"></div>
                                <div class="h-12 rounded-xl bg-slate-50"></div>
                                <div class="h-12 rounded-xl bg-slate-50"></div>
                            </div>

                        </div>

                    </div>
                </div>

            </div>
        </div>
    </section>


    <!-- ================= CAREER ================= -->
    <section id="career" class="py-20 lg:py-24">
        <div class="mx-auto max-w-7xl px-5 lg:px-8">

            <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p class="text-sm font-bold uppercase tracking-widest text-indigo-600">
                        Career resources
                    </p>

                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">
                        Level up your career
                    </h2>

                    <p class="mt-3 text-slate-500">
                        Practical advice to help you get hired and grow faster.
                    </p>
                </div>

                <a href="#" class="text-sm font-bold text-indigo-600">
                    Explore resources →
                </a>
            </div>

            <div class="mt-10 grid gap-5 md:grid-cols-3">

                <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-xl">

                    <div class="flex h-44 items-center justify-center bg-indigo-50">
                        <i data-lucide="file-text" class="h-16 w-16 text-indigo-300"></i>
                    </div>

                    <div class="p-6">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">
                            Resume
                        </span>

                        <h3 class="mt-3 text-lg font-bold">
                            How to create a resume that gets noticed
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Simple strategies to make your resume stand out to recruiters.
                        </p>

                        <a href="#" class="mt-5 inline-block text-sm font-bold text-slate-900">
                            Read article →
                        </a>
                    </div>

                </article>


                <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-xl">

                    <div class="flex h-44 items-center justify-center bg-pink-50">
                        <i data-lucide="messages-square" class="h-16 w-16 text-pink-300"></i>
                    </div>

                    <div class="p-6">
                        <span class="text-xs font-bold uppercase tracking-wider text-pink-600">
                            Interview
                        </span>

                        <h3 class="mt-3 text-lg font-bold">
                            Ace your next job interview
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Learn how to answer common interview questions with confidence.
                        </p>

                        <a href="#" class="mt-5 inline-block text-sm font-bold text-slate-900">
                            Read article →
                        </a>
                    </div>

                </article>


                <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:shadow-xl">

                    <div class="flex h-44 items-center justify-center bg-emerald-50">
                        <i data-lucide="trending-up" class="h-16 w-16 text-emerald-300"></i>
                    </div>

                    <div class="p-6">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">
                            Career
                        </span>

                        <h3 class="mt-3 text-lg font-bold">
                            Skills that employers want in 2026
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Discover the skills that can help accelerate your career.
                        </p>

                        <a href="#" class="mt-5 inline-block text-sm font-bold text-slate-900">
                            Read article →
                        </a>
                    </div>

                </article>

            </div>
        </div>
    </section>


    <!-- ================= EMPLOYER CTA ================= -->
    <section class="bg-slate-950 py-20 text-white lg:py-24">
        <div class="mx-auto max-w-7xl px-5 lg:px-8">

            <div class="grid items-center gap-12 lg:grid-cols-2">

                <div>
                    <p class="text-sm font-bold uppercase tracking-widest text-indigo-400">
                        For employers
                    </p>

                    <h2 class="mt-3 text-3xl font-extrabold leading-tight sm:text-4xl">
                        Hire great people.
                        Build something great.
                    </h2>

                    <p class="mt-5 max-w-xl leading-7 text-slate-400">
                        Reach talented candidates, manage applications and build
                        your dream team from one simple platform.
                    </p>

                    <button class="mt-8 rounded-xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-indigo-500">
                        Start Hiring
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <i data-lucide="users" class="h-7 w-7 text-indigo-400"></i>
                        <div class="mt-5 text-3xl font-extrabold">850K+</div>
                        <p class="mt-1 text-sm text-slate-400">Active candidates</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <i data-lucide="building-2" class="h-7 w-7 text-indigo-400"></i>
                        <div class="mt-5 text-3xl font-extrabold">12K+</div>
                        <p class="mt-1 text-sm text-slate-400">Companies</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <i data-lucide="send" class="h-7 w-7 text-indigo-400"></i>
                        <div class="mt-5 text-3xl font-extrabold">2.4M+</div>
                        <p class="mt-1 text-sm text-slate-400">Applications</p>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <i data-lucide="badge-check" class="h-7 w-7 text-indigo-400"></i>
                        <div class="mt-5 text-3xl font-extrabold">94%</div>
                        <p class="mt-1 text-sm text-slate-400">Hiring success</p>
                    </div>

                </div>

            </div>
        </div>
    </section>


    <!-- ================= FOOTER ================= -->
    <?php include 'includes/footer.php'; ?>



    <script src="<?= MAIN_URL ?>javascript/main.js"></script>

</body>

</html>