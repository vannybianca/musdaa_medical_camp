const menuToggle = document.querySelector('.menu-toggle');
const mainMenu = document.getElementById('main-menu');

if (menuToggle && mainMenu) {
    let suppressNextClick = false;

    const closeMenu = () => {
        mainMenu.classList.remove('is-open');
        menuToggle.setAttribute('aria-expanded', 'false');
    };

    const toggleMenu = (event) => {
        if (event) {
            event.preventDefault();
        }

        if (event && event.type === 'touchstart') {
            if (suppressNextClick) {
                return;
            }
            suppressNextClick = true;
            setTimeout(() => {
                suppressNextClick = false;
            }, 300);
        }

        const isOpen = mainMenu.classList.toggle('is-open');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    menuToggle.addEventListener('click', (event) => {
        if (suppressNextClick) {
            suppressNextClick = false;
            event.preventDefault();
            return;
        }
        toggleMenu(event);
    });

    menuToggle.addEventListener('touchstart', toggleMenu, { passive: false });

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
