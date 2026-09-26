const menuToggle = document.querySelector('.menu-toggle');
const mainMenu = document.getElementById('main-menu');

if (menuToggle && mainMenu) {
    const closeMenu = () => {
        mainMenu.classList.remove('is-open');
        menuToggle.setAttribute('aria-expanded', 'false');
    };

    menuToggle.addEventListener('click', () => {
        const isOpen = mainMenu.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    mainMenu.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            closeMenu();
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.topbar')) {
            closeMenu();
        }
    });
}
