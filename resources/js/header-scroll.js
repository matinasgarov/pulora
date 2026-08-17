/**
 * Switches the header out of its dark-glass state once the page scrolls.
 *
 * Only pages that opt in (data-overlay, set when the page opens on a dark hero)
 * have anything to switch — everywhere else the header is already the solid
 * cream bar and this does nothing. That is also what happens without
 * JavaScript: the readable state is the default, and this only ever takes the
 * page *out* of the decorative one.
 *
 * The threshold is deliberately small. The point is to leave the glass the
 * moment the hero starts moving, not to wait for it to clear the viewport.
 */
const THRESHOLD = 40;

export default function initHeaderScroll() {
    const header = document.querySelector('[data-site-header][data-overlay]');

    if (!header) {
        return;
    }

    let pending = false;

    const sync = () => {
        pending = false;

        if (window.scrollY > THRESHOLD) {
            header.setAttribute('data-scrolled', '');
        } else {
            header.removeAttribute('data-scrolled');
        }
    };

    // Scroll fires far more often than the screen repaints, and this only ever
    // results in one attribute being set, so coalesce to a frame.
    window.addEventListener('scroll', () => {
        if (!pending) {
            pending = true;
            window.requestAnimationFrame(sync);
        }
    }, { passive: true });

    // A reload part-way down a page restores the scroll position without ever
    // firing a scroll event, which would otherwise leave a glass header over
    // pale page content.
    sync();
}
