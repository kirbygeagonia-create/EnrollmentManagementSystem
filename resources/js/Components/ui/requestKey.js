/**
 * The key that identifies one desk act, for ADS guide problem 17.
 *
 * A desk that posts an enrollment and loses the response — power out, browser closed, network dropped — must be
 * able to send the same act again and be answered with the record that already exists rather than a second one.
 * That only works if the key names the ACT and not the screen, so it is minted when the form is opened and
 * replaced the moment the act succeeds: a page that issues five students in one visit uses five keys.
 *
 * `crypto.randomUUID()` exists only in a secure context. The offices reach this app over http on a LAN address,
 * where it is absent, so the fallback is the same shape the server expects (a short unique token) rather than an
 * invented domain value — the key carries no meaning beyond identity, and a blank or reused key would.
 */
export function newRequestKey(prefix = 'act') {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return `${prefix}-${crypto.randomUUID()}`.slice(0, 64);
    }

    // 64 is the column's own width; a longer key would be refused by validation rather than stored truncated.
    return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`.slice(0, 64);
}
