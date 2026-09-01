/** The slice of the browser the runner touches, and nothing more. */

export function installDom() {
    const store = new Map();

    globalThis.sessionStorage = {
        getItem: (k) => (store.has(k) ? store.get(k) : null),
        setItem: (k, v) => store.set(k, String(v)),
        removeItem: (k) => store.delete(k),
    };

    globalThis.window = {
        location: { origin: 'https://helpdesk.test', pathname: '/' },
        matchMedia: () => ({ matches: false }),
        addEventListener() {},
        removeEventListener() {},
    };

    globalThis.document = {
        body: { dataset: {}, appendChild() {}, contains: () => true },
        createElement: () => ({ style: {}, setAttribute() {}, querySelector: () => null, remove() {}, appendChild() {} }),
        querySelector: () => null,
        addEventListener() {},
        removeEventListener() {},
    };

    globalThis.MutationObserver = class {
        observe() {}
        disconnect() {}
    };
}
