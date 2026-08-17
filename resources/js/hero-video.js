/**
 * Decides whether this visitor gets the hero's video loop.
 *
 * The photograph is always the hero. The loop is an upgrade for people who can
 * afford it, so it is withheld from the people who cannot rather than offered
 * to everyone and cancelled afterwards — by the time you cancel, the bytes are
 * already paid for. Nothing is fetched until every gate below has opened.
 */

const WIDE = '(min-width: 900px)';

function play(video) {
    if (video.dataset.loaded) {
        return;
    }

    video.dataset.loaded = 'true';

    JSON.parse(video.dataset.sources).forEach(({ src, type }) => {
        const source = document.createElement('source');
        source.src = src;
        source.type = type;
        video.appendChild(source);
    });

    // Only reveal it once frames are actually running. A video that stalls, or
    // that autoplay policy silently refuses, then looks like no video at all
    // rather than like a black rectangle over the photograph.
    video.addEventListener('playing', () => video.classList.remove('opacity-0'), { once: true });

    video.load();
    video.play().catch(() => {
        // Autoplay refused. The photograph is already the right thing to show.
        video.remove();
    });
}

export default function initHeroVideo() {
    const video = document.querySelector('[data-hero-video]');

    if (!video) {
        return;
    }

    // Someone who has asked for less motion has asked for this exact thing.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        video.remove();

        return;
    }

    // Save-Data is an explicit request not to spend the visitor's money.
    if (navigator.connection?.saveData) {
        video.remove();

        return;
    }

    // Phones get the photograph. Most of this shop's traffic arrives from
    // Instagram on a phone, and a hero loop there is megabytes spent before
    // anyone has seen a price.
    const wide = window.matchMedia(WIDE);

    if (wide.matches) {
        play(video);
    }

    // A window widened past the breakpoint — a desktop browser starting small,
    // or a tablet turned — is a visitor who now qualifies.
    wide.addEventListener('change', (event) => event.matches && play(video));
}
