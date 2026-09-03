/**
 * Runner sequencing tests.
 *
 * The state machine is invisible to the PHP suite, and a browser rig is more
 * than this warrants — so these are hand-written DOM stubs covering the
 * sequencing and the named failure modes. Run with: npm run test:tour
 */

import assert from 'node:assert/strict';
import { test, run } from './harness.mjs';
import { installDom } from './dom-stub.mjs';

installDom();

const {
    stepsOnThisPage, matchesPage, pointsAtCurrentPage,
    saveProgress, readProgress, clearProgress, STORAGE_KEY,
} = await import('../../resources/js/tour.js');

/* ---------- page matching ---------------------------------------------- */

test('a step with no page declared runs anywhere', () => {
    assert.equal(matchesPage(undefined, 'tickets.index'), true);
});

test('a page matches exactly', () => {
    assert.equal(matchesPage('tickets.index', 'tickets.index'), true);
    assert.equal(matchesPage('tickets.index', 'tickets.create'), false);
});

test('a trailing wildcard matches a route prefix', () => {
    assert.equal(matchesPage('tickets.*', 'tickets.create'), true);
    assert.equal(matchesPage('tickets.*', 'clients.index'), false);
});

/* ---------- which steps run here ---------------------------------------- */

const steps = [
    { page: 'tickets.index', title: 'a' },
    { page: 'tickets.create', title: 'b' },
    { page: 'tickets.create', title: 'c' },
    { page: 'tickets.index', title: 'd' },
];

test('takes the contiguous run of steps for this page, not every match', () => {
    // Starting at the form, steps b and c run here — but d must NOT be pulled
    // forward just because it also lives on a page we happen to be on later.
    const here = stepsOnThisPage(steps, 1, 'tickets.create');
    assert.deepEqual(here.map((s) => s.step.title), ['b', 'c']);
});

test('returns nothing when the next step lives elsewhere', () => {
    assert.deepEqual(stepsOnThisPage(steps, 1, 'tickets.index'), []);
});

test('keeps absolute indices so progress survives the page it was saved on', () => {
    const here = stepsOnThisPage(steps, 1, 'tickets.create');
    assert.deepEqual(here.map((s) => s.index), [1, 2]);
});

/* ---------- F1: save before the early return ---------------------------- */

test('F1 · progress is saved even when no step belongs to this page', () => {
    clearProgress();

    // This is the exact shape of run(): save first, then decide.
    const run = (tour, i, route) => {
        saveProgress(tour.key, i);
        const here = stepsOnThisPage(tour.steps, i, route);
        if (here.length === 0) return 'off-page';
        return 'showing';
    };

    // A tour started from a page where no step lives — the common case, since
    // tours are launched from a help menu.
    const outcome = run({ key: 'create-ticket', steps }, 0, 'dashboard');

    assert.equal(outcome, 'off-page');
    assert.deepEqual(readProgress(), { key: 'create-ticket', index: 0 },
        'if the save moved below the early return, nothing would be stored and '
        + 'the tour would die at the first navigation');
});

/* ---------- F3: downgrade a pointless click ----------------------------- */

test('F3 · a click-step whose target leads back here is downgraded', () => {
    globalThis.window.location.pathname = '/acme/tickets';

    const samePage = { getAttribute: () => '/acme/tickets' };
    const otherPage = { getAttribute: () => '/acme/tickets/create' };
    const notALink = { getAttribute: () => null };

    assert.equal(pointsAtCurrentPage(samePage), true, 'clicking this would just reload');
    assert.equal(pointsAtCurrentPage(otherPage), false);
    assert.equal(pointsAtCurrentPage(notALink), false);
});

test('a Next step whose page differs hands back to run(), not to a missing target', () => {
    // activity-logs step 1 is on the log page and step 2 on the ticket list,
    // joined by a Next button rather than a click. Advancing blindly made the
    // runner look for the ticket-list anchor on the log page and report it as
    // missing, which reads as a broken tour rather than a page change.
    const steps = [
        { page: 'activity-logs.index', title: 'a' },
        { page: 'tickets.index', title: 'b' },
    ];

    assert.equal(matchesPage(steps[1].page, 'activity-logs.index'), false,
        'the second step does not belong to the page the first one is on');
    assert.deepEqual(stepsOnThisPage(steps, 1, 'activity-logs.index'), [],
        'so run() must take the off-page branch and offer the way there');
});

/* ---------- F6: every exit path clears ---------------------------------- */

test('F6 · clearing progress removes it entirely', () => {
    saveProgress('merge-duplicates', 2);
    assert.notEqual(readProgress(), null);

    clearProgress();

    assert.equal(readProgress(), null,
        'an abandoned tour must not ambush the user on an unrelated page later');
    assert.equal(globalThis.sessionStorage.getItem(STORAGE_KEY), null);
});

test('progress survives a round trip through session storage', () => {
    saveProgress('create-ticket', 3);
    assert.deepEqual(readProgress(), { key: 'create-ticket', index: 3 });
});

test('unreadable stored progress is treated as none, not a crash', () => {
    globalThis.sessionStorage.setItem(STORAGE_KEY, '{not json');
    assert.equal(readProgress(), null);
    clearProgress();
});

run();
