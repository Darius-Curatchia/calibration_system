const sidebar = document.getElementById('sidebar');
const toggle = document.getElementById('sidebarToggle');
const header = document.querySelector('.header');
const main = document.querySelector('.main-content');

// Toggle sidebar and adjust header/main
toggle.addEventListener('click', () => {
    const isCollapsed = sidebar.classList.toggle('collapsed');

    header.classList.toggle('sidebar-collapsed', isCollapsed);
    main.classList.toggle('sidebar-collapsed', isCollapsed);

    localStorage.setItem('sidebarCollapsed', isCollapsed);
});

// Restore previous state
const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
if (savedState) {
    sidebar.classList.add('collapsed');
    header.classList.add('sidebar-collapsed');
    main.classList.add('sidebar-collapsed');
}
