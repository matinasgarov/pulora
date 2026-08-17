/**
 * Progressive enhancement for the product gallery.
 *
 * The markup is already a working horizontal scroller with anchor thumbnails.
 * This adds the two things anchors and scroll-snap cannot do: arrows, and zoom.
 *
 * Everything here reads position from the track's own scrollLeft rather than
 * keeping an index in a variable. A swipe, a trackpad flick and an arrow press
 * all move the same scroller, so a separate index would drift out of step with
 * what is actually on screen.
 */

const ZOOM = 2.2;

function setUp(gallery) {
    const track = gallery.querySelector('[data-gallery-track]');
    const frames = [...gallery.querySelectorAll('[data-gallery-frame]')];

    if (!track || frames.length === 0) {
        return;
    }

    const current = () => Math.round(track.scrollLeft / track.clientWidth);

    const goTo = (index) => {
        track.scrollTo({
            left: Math.max(0, Math.min(frames.length - 1, index)) * track.clientWidth,
            behavior: 'smooth',
        });
    };

    // --- arrows -------------------------------------------------------------

    const prev = gallery.querySelector('[data-gallery-prev]');
    const next = gallery.querySelector('[data-gallery-next]');
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];

    if (prev && next) {
        prev.hidden = false;
        next.hidden = false;

        prev.addEventListener('click', () => goTo(current() - 1));
        next.addEventListener('click', () => goTo(current() + 1));
    }

    const syncControls = () => {
        const index = current();

        // At an end the arrow has nothing to do, so it goes away rather than
        // sitting there doing nothing when pressed.
        if (prev && next) {
            prev.hidden = index === 0;
            next.hidden = index === frames.length - 1;
        }

        thumbs.forEach((thumb, i) => {
            thumb.setAttribute('aria-current', String(i === index));
        });
    };

    track.addEventListener('scroll', () => {
        window.cancelAnimationFrame(track.dataset.raf);
        track.dataset.raf = window.requestAnimationFrame(syncControls);
    });

    window.addEventListener('resize', syncControls);
    syncControls();

    thumbs.forEach((thumb, index) => {
        thumb.addEventListener('click', (event) => {
            // The anchor is the no-JS path. Taking it here would put a hash in
            // the address bar and let the browser scroll the page as well as
            // the track.
            event.preventDefault();
            goTo(index);
        });
    });

    gallery.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goTo(current() - 1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            goTo(current() + 1);
        }
    });

    // --- zoom ---------------------------------------------------------------

    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');

    frames.forEach((frame) => {
        const image = frame.querySelector('[data-gallery-image]');

        if (!image) {
            return;
        }

        // Origin follows the cursor, so the point under the pointer is the point
        // magnified — the whole reason to zoom a stitch or an edge paint.
        const originFrom = (event) => {
            const box = frame.getBoundingClientRect();

            image.style.transformOrigin =
                `${((event.clientX - box.left) / box.width) * 100}% ` +
                `${((event.clientY - box.top) / box.height) * 100}%`;
        };

        const zoom = (event) => {
            originFrom(event);
            image.style.transform = `scale(${ZOOM})`;
            frame.style.cursor = 'zoom-out';
        };

        const reset = () => {
            image.style.transform = '';
            frame.style.cursor = finePointer.matches ? 'zoom-in' : '';
        };

        const applyPointerMode = () => {
            reset();

            if (!finePointer.matches) {
                // No hover to leave on a touch screen, so the same tap that
                // zooms in has to be able to zoom back out.
                frame.onclick = (event) => {
                    image.style.transform ? reset() : zoom(event);
                };
                frame.onmousemove = null;
                frame.onmouseleave = null;

                return;
            }

            frame.onclick = null;
            frame.onmousemove = zoom;
            frame.onmouseleave = reset;
        };

        applyPointerMode();
        finePointer.addEventListener('change', applyPointerMode);
    });
}

export default function initProductGallery() {
    document.querySelectorAll('[data-gallery]').forEach(setUp);
}
