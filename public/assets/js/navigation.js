document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('nav-toggle');
    const nav = document.getElementById('primary-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            nav.classList.toggle('open', !expanded);
        });
    }
    document.querySelectorAll('form[method="POST"]').forEach(form => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (button && form.checkValidity()) {
                button.disabled = true;
                button.textContent = 'Memproses...';
            }
        });
    });
    const filterForm = document.querySelector('.jobs-filter-form');
    const loading = document.getElementById('jobs-loading');
    if (filterForm && loading) {
        filterForm.addEventListener('submit', () => {
            document.getElementById('jobs-list').hidden = true;
            loading.hidden = false;
        });
    }
});
