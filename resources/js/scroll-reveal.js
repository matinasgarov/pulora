/**
 * Fades sections in as they come into view.
 *
 * The hidden state lives in CSS behind `.js`, so nothing is ever hidden from a
 * visitor without a script. Each element is unobserved once shown.
 */
const STAGGER_MS = 60;
const MAX_STEPS = 6;

export default function initScrollReveal() {
    const targets = document.querySelectorAll('[data-reveal]');

    if (targets.length === 0 || !('IntersectionObserver' in window)) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        // Index within this batch, not within the whole grid: numbering all 15
        // tiles would leave the last waiting a second after the first.
        let step = 0;

        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.style.setProperty('--reveal-delay', `${Math.min(step, MAX_STEPS) * STAGGER_MS}ms`);
            entry.target.setAttribute('data-revealed', '');
            observer.unobserve(entry.target);
            step++;
        });
    }, { rootMargin: '0px 0px -10% 0px' });

    targets.forEach((target) => observer.observe(target));
}
