// ================= JAVASCRIPT ================= 

// Initialize Lucide icons
lucide.createIcons();


// Mobile menu
const menuBtn = document.getElementById('menuBtn');
const mobileMenu = document.getElementById('mobileMenu');

menuBtn.addEventListener('click', () => {
    mobileMenu.classList.toggle('active');

    const icon = menuBtn.querySelector('svg');

    if (mobileMenu.classList.contains('active')) {
        menuBtn.innerHTML = '<i data-lucide="x" class="h-5 w-5"></i>';
    } else {
        menuBtn.innerHTML = '<i data-lucide="menu" class="h-5 w-5"></i>';
    }

    lucide.createIcons();
});


// Search
const searchInput = document.getElementById('searchInput');
const searchBtn = document.getElementById('searchBtn');
const jobCards = document.querySelectorAll('.job-card');
const noJobs = document.getElementById('noJobs');

function searchJobs() {

    const keyword = searchInput.value.toLowerCase().trim();

    let found = 0;

    jobCards.forEach(card => {

        const title = card.dataset.title.toLowerCase();

        if (!keyword || title.includes(keyword)) {
            card.style.display = '';
            found++;
        } else {
            card.style.display = 'none';
        }

    });

    noJobs.classList.toggle('hidden', found !== 0);
}

searchBtn.addEventListener('click', searchJobs);

searchInput.addEventListener('keydown', (event) => {

    if (event.key === 'Enter') {
        searchJobs();
    }

});


// Popular searches
document.querySelectorAll('.popular-search').forEach(button => {

    button.addEventListener('click', () => {

        searchInput.value = button.textContent.trim();

        searchJobs();

        document.getElementById('jobs').scrollIntoView({
            behavior: 'smooth'
        });

    });

});


// Bookmark buttons
document.querySelectorAll('.bookmark').forEach(button => {

    button.addEventListener('click', () => {

        button.classList.toggle('text-indigo-600');
        button.classList.toggle('bg-indigo-50');

    });

});
