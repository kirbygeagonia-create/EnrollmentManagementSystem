import { useSyncExternalStore } from 'react';
import { router } from '@inertiajs/react';

/**
 * Whether Inertia is waiting on the server — one subscription for the whole app.
 *
 * Prompt §14 asks for a loading state, and the desk's real wait is not a form submit: it is the moment a filter, a
 * sort or a picker is clicked and the rows still on screen are the previous answer while the new one is in flight.
 * `useForm().processing` does not cover that, because it belongs to the act that posted and not to the visit that
 * refetched the list — measuring it showed 24 pages crediting themselves with exactly that unrelated flag, on a
 * different control.
 *
 * The state is therefore read from the router, and subscribed ONCE at boot rather than per component. That is not a
 * style preference: `router.on(event, callback)` returns an unsubscribe whose filter is
 * `listener.event !== event && listener.callback !== callback`, which drops every listener registered for that event
 * name — so a page holding two tables and unsubscribing one would silently stop the other from ever clearing its
 * "Updating" caption. A single app-level subscription with external readers cannot do that.
 */
let pending = false;
const readers = new Set();
let subscribed = false;

function setPending(next) {
    if (pending === next) {
        return;
    }
    pending = next;
    readers.forEach((notify) => notify());
}

/** Register the router listeners. Idempotent, because a second call would add a second pair of listeners. */
export function watchRouterPending() {
    if (subscribed) {
        return;
    }
    subscribed = true;
    router.on('start', () => setPending(true));
    // `finish` fires when a visit has finished whether it succeeded or not, and `cancel` fires when a newer visit
    // supersedes this one — a cancelled visit never reports a completion, so both are needed or a desk is left
    // reading "Updating" for a request that no longer exists.
    ['finish', 'cancel'].forEach((event) => router.on(event, () => setPending(false)));
}

function subscribe(notify) {
    readers.add(notify);

    return () => readers.delete(notify);
}

export function useRouterPending() {
    return useSyncExternalStore(subscribe, () => pending, () => false);
}

export default useRouterPending;
