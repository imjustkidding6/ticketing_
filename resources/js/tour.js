/**
 * Guided tour runner.
 *
 * A tour is a state machine, not a slideshow: it spans page loads, resumes
 * itself, and lets the user drive. See config/tours.php for the definitions.
 *
 * The overlay is four parts — a dimming backdrop, a cut-out that leaves the
 * target lit, a panel, and a pointer. The backdrop and cut-out are one element:
 * a transparent box with a very large box-shadow spread.
 */

const STORAGE_KEY = 'guided-tour-progress';
const SETTLE_MS = 120;
const TARGET_WAIT_MS = 3000;

/* ---------- progress ---------------------------------------------------- */

// Session storage, keyed per tab: a tour is not a cross-device concept, and
// someone who abandoned one on Tuesday does not want it resuming on Thursday.
function readProgress() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function saveProgress(key, index) {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ key, index }));
    } catch {
        /* private mode — the tour still works, it just will not resume */
    }
}

function clearProgress() {
    try {
        sessionStorage.removeItem(STORAGE_KEY);
    } catch {
        /* ignore */
    }
}

/* ---------- DOM helpers ------------------------------------------------- */

/**
 * F2: a target inside a collapsed accordion or closed sub-menu exists in the
 * DOM with zero dimensions, so the overlay computes an empty rectangle. Walk
 * the ancestors and open anything closed, the way the application opens it.
 */
function reveal(el) {
    if (!el) return;

    for (let node = el.parentElement; node; node = node.parentElement) {
        if (node.tagName === 'DETAILS' && !node.open) {
            node.open = true;
        }

        if (node.hasAttribute?.('hidden')) {
            node.hidden = false;
        }

        // Alpine collapses with x-show, which sets an inline display:none.
        if (node.style?.display === 'none') {
            node.style.removeProperty('display');
        }

        if (node.getAttribute?.('aria-expanded') === 'false') {
            node.setAttribute('aria-expanded', 'true');
        }
    }

    el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
}

function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function isVisible(el) {
    if (!el) return false;
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
}

/**
 * F4: data tables and lazy panels draw their controls after page load, so
 * resolve selectors at highlight time and wait briefly with an observer rather
 * than a fixed timeout.
 */
function waitForTarget(selector, timeout = TARGET_WAIT_MS) {
    const immediate = document.querySelector(selector);
    if (immediate && isVisible(immediate)) return Promise.resolve(immediate);

    return new Promise((resolve) => {
        let settled = false;
        const finish = (el) => {
            if (settled) return;
            settled = true;
            observer.disconnect();
            clearTimeout(timer);
            resolve(el);
        };

        const observer = new MutationObserver(() => {
            const el = document.querySelector(selector);
            if (el && isVisible(el)) finish(el);
        });

        observer.observe(document.body, { childList: true, subtree: true, attributes: true });
        const timer = setTimeout(() => finish(document.querySelector(selector)), timeout);
    });
}

/**
 * F3: the first step on a page is usually the navigation item that got the
 * user there, and clicking it again just reloads. If a click-step's target
 * resolves to the current page, downgrade it to a Next step.
 */
function pointsAtCurrentPage(el) {
    const href = el?.getAttribute?.('href');
    if (!href) return false;

    try {
        const url = new URL(href, window.location.origin);
        return url.pathname === window.location.pathname;
    } catch {
        return false;
    }
}

function matchesPage(stepPage, currentRoute) {
    if (!stepPage) return true;
    if (stepPage.endsWith('*')) {
        return currentRoute.startsWith(stepPage.slice(0, -1));
    }
    return stepPage === currentRoute;
}

/**
 * The contiguous run of steps from `index` that belong to the current page.
 *
 * Contiguous, not "all matching": a tour that goes list → form → list must
 * stop at the form rather than skipping ahead to the second list step.
 */
function stepsOnThisPage(steps, index, currentRoute) {
    const run = [];
    for (let i = index; i < steps.length; i++) {
        if (!matchesPage(steps[i].page, currentRoute)) break;
        run.push({ step: steps[i], index: i });
    }
    return run;
}

/* ---------- overlay ----------------------------------------------------- */

class Overlay {
    constructor() {
        this.root = document.createElement('div');
        this.root.className = 'gt-root';
        this.root.setAttribute('data-tour-overlay', '');
        this.root.innerHTML = `
            <div class="gt-cutout" data-gt="cutout"></div>
            <div class="gt-pointer" data-gt="pointer" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="30" height="30">
                    <path d="M5 2l14 8-6 1.6L9.8 18z" fill="#fff" stroke="#1f2937" stroke-width="1.4" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="gt-panel" data-gt="panel" role="dialog" aria-modal="false" aria-live="polite" tabindex="-1">
                <button type="button" class="gt-close" data-gt="close" aria-label="End tour">&times;</button>
                <h2 class="gt-title" data-gt="title"></h2>
                <p class="gt-body" data-gt="body"></p>
                <div class="gt-foot">
                    <span class="gt-step" data-gt="progress"></span>
                    <div class="gt-actions">
                        <button type="button" class="gt-secondary" data-gt="back">Back</button>
                        <button type="button" class="gt-primary" data-gt="next">Next</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(this.root);

        this.el = (name) => this.root.querySelector(`[data-gt="${name}"]`);
        this.reposition = this.reposition.bind(this);
        window.addEventListener('resize', this.reposition, { passive: true });
        window.addEventListener('scroll', this.reposition, { passive: true, capture: true });
    }

    /** Point the cut-out, pointer and panel at an element. */
    focus(target, side = 'bottom') {
        this.target = target;
        this.side = side;
        this.reposition();
    }

    reposition() {
        if (!this.target || !document.body.contains(this.target)) return;

        const r = this.target.getBoundingClientRect();
        const pad = 6;

        const cutout = this.el('cutout');
        cutout.style.top = `${r.top - pad}px`;
        cutout.style.left = `${r.left - pad}px`;
        cutout.style.width = `${r.width + pad * 2}px`;
        cutout.style.height = `${r.height + pad * 2}px`;

        const pointer = this.el('pointer');
        pointer.style.top = `${r.top + r.height * 0.55}px`;
        pointer.style.left = `${r.left + r.width * 0.5}px`;

        // The panel must stay in the viewport and must never cover its target.
        const panel = this.el('panel');
        const pr = panel.getBoundingClientRect();
        const gap = 14;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        let top;
        let left;

        const below = r.bottom + gap;
        const above = r.top - pr.height - gap;

        if (this.side === 'top' && above > 8) {
            top = above;
        } else if (below + pr.height < vh - 8) {
            top = below;
        } else if (above > 8) {
            top = above;
        } else {
            // No room either side of the target: sit clear of it vertically.
            top = Math.max(8, Math.min(vh - pr.height - 8, r.top));
        }

        left = r.left + r.width / 2 - pr.width / 2;
        left = Math.max(8, Math.min(left, vw - pr.width - 8));

        panel.style.top = `${Math.max(8, top)}px`;
        panel.style.left = `${left}px`;
    }

    /** B7: say so, rather than dimming the screen and spotlighting nothing. */
    showMissingTarget(step, { onSkip, onEnd }) {
        this.el('cutout').style.opacity = '0';
        this.el('pointer').style.display = 'none';
        this.el('title').textContent = 'That step is not on this screen';
        this.el('body').textContent =
            `"${step.title}" points at something this page does not have — it may need a different plan, `
            + 'a permission you do not hold, or it has moved since the tour was written.';
        this.el('next').textContent = 'Skip this step';
        this.el('back').hidden = true;
        this.el('next').onclick = onSkip;
        this.el('close').onclick = onEnd;
        this.el('panel').style.top = '50%';
        this.el('panel').style.left = '50%';
        this.el('panel').style.transform = 'translate(-50%, -50%)';
        this.el('panel').focus();
    }

    /** A step that lives on another page: offer the way forward, don't guess. */
    showOffPage(step, { href, onEnd }) {
        this.el('cutout').style.opacity = '0';
        this.el('pointer').style.display = 'none';
        this.el('title').textContent = 'Continue on another page';
        this.el('body').textContent = `The next step, "${step.title}", is on a different screen.`;
        this.el('next').textContent = href ? 'Take me there' : 'End tour';
        this.el('back').hidden = true;
        this.el('next').onclick = href ? () => { window.location.href = href; } : onEnd;
        this.el('close').onclick = onEnd;
        this.el('panel').style.top = '50%';
        this.el('panel').style.left = '50%';
        this.el('panel').style.transform = 'translate(-50%, -50%)';
        this.el('panel').focus();
    }

    destroy() {
        window.removeEventListener('resize', this.reposition);
        window.removeEventListener('scroll', this.reposition, { capture: true });
        this.root.remove();
    }
}

/* ---------- the runner -------------------------------------------------- */

class TourRunner {
    constructor({ currentRoute, endpoint }) {
        this.currentRoute = currentRoute;
        this.endpoint = endpoint;
        this.overlay = null;
        this.tour = null;
        this.onKeydown = this.onKeydown.bind(this);
    }

    async fetchTour(key, isStart = false) {
        const url = `${this.endpoint}/${encodeURIComponent(key)}${isStart ? '?start=1' : ''}`;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });

        if (!res.ok) {
            clearProgress();
            return null;
        }

        const data = await res.json();
        return data.tour ?? null;
    }

    /** Explicit start: clears any half-finished tour first. B1 — always allowed. */
    async start(key) {
        clearProgress();
        const tour = await this.fetchTour(key, true);
        if (!tour) return;
        this.run(tour, 0);
    }

    /** Resume after a page load. */
    async resume() {
        const progress = readProgress();
        if (!progress?.key) return;

        const tour = await this.fetchTour(progress.key, false);
        if (!tour) return;

        // F4: give async widgets a moment to draw before we look for targets.
        setTimeout(() => this.run(tour, progress.index ?? 0), SETTLE_MS);
    }

    run(tour, index) {
        // F1: SAVE FIRST, before any early return. Tours are started from a
        // page where no step lives, so the off-page branch below runs on the
        // very first call — if the save came after it, nothing would ever be
        // stored and the tour would die at the first navigation.
        saveProgress(tour.key, index);

        this.tour = tour;

        const here = stepsOnThisPage(tour.steps, index, this.currentRoute);

        if (!this.overlay) {
            this.overlay = new Overlay();
            document.addEventListener('keydown', this.onKeydown);
            this.overlay.el('close').onclick = () => this.end();
        }

        if (here.length === 0) {
            const step = tour.steps[index];
            if (!step) return this.end();

            this.overlay.showOffPage(step, {
                href: null,
                // F6: closing this prompt is an exit path too, so it clears.
                onEnd: () => this.end(),
            });
            return;
        }

        this.showStep(index);
    }

    async showStep(index) {
        const step = this.tour.steps[index];
        if (!step) return this.finish();

        // Save on every step, so a navigation mid-tour resumes in the right place.
        saveProgress(this.tour.key, index);
        this.index = index;

        const target = await waitForTarget(step.selector);

        if (!target || !isVisible(target)) {
            this.overlay.showMissingTarget(step, {
                onSkip: () => this.showStep(index + 1),
                onEnd: () => this.end(),
            });
            return;
        }

        // Re-reveal on every step: the user may have closed the container.
        reveal(target);

        const panel = this.overlay.el('panel');
        panel.style.transform = '';
        this.overlay.el('cutout').style.opacity = '';
        this.overlay.el('pointer').style.display = '';

        this.overlay.el('title').textContent = step.title;
        this.overlay.el('body').textContent = step.body;
        this.overlay.el('progress').textContent = `Step ${index + 1} of ${this.tour.steps.length}`;

        const back = this.overlay.el('back');
        back.hidden = index === 0;
        back.onclick = () => this.showStep(index - 1);

        const next = this.overlay.el('next');
        const isLast = index === this.tour.steps.length - 1;

        // B3 / F3: a click-step whose target just leads back here is really an
        // explain-step, so downgrade it rather than asking for a pointless click.
        const wantsClick = step.advance === 'click' && !pointsAtCurrentPage(target);

        if (wantsClick) {
            next.hidden = true;
            this.overlay.el('progress').textContent =
                `Step ${index + 1} of ${this.tour.steps.length} — click the highlighted control to continue`;

            const onClick = () => {
                target.removeEventListener('click', onClick);
                // Save the NEXT index, then let the click do what it normally
                // does. B6: we never trigger it ourselves.
                saveProgress(this.tour.key, index + 1);
            };
            target.addEventListener('click', onClick);
            this.cleanupTarget = () => target.removeEventListener('click', onClick);
        } else {
            next.hidden = false;
            next.textContent = isLast ? 'Finish' : 'Next';
            next.onclick = () => (isLast ? this.finish() : this.showStep(index + 1));
        }

        this.overlay.focus(target, step.side ?? 'bottom');
        panel.focus();
    }

    onKeydown(event) {
        // B4: Escape ends it, at zero cost.
        if (event.key === 'Escape') this.end();
    }

    finish() {
        this.end();
    }

    /** Every exit path clears progress — F6. */
    end() {
        this.cleanupTarget?.();
        this.cleanupTarget = null;
        clearProgress();
        document.removeEventListener('keydown', this.onKeydown);
        this.overlay?.destroy();
        this.overlay = null;
        this.tour = null;
    }
}

export {
    TourRunner,
    Overlay,
    stepsOnThisPage,
    readProgress,
    saveProgress,
    clearProgress,
    reveal,
    pointsAtCurrentPage,
    matchesPage,
    isVisible,
    waitForTarget,
    STORAGE_KEY,
};
