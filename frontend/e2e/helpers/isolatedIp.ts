/**
 * Retained for Node-side security probes only.
 *
 * A browser page must NOT set X-Forwarded-For via `setExtraHTTPHeaders`: that
 * turns it into a custom cross-origin header, and the backend CORS policy
 * intentionally does not allow it, so every API preflight fails. Browser
 * logins therefore use the shared IP (sparingly), while role matrices use
 * `loginToAdminViaApi` from adminLogin.ts.
 */
export const isolateRequestIp = (ip: string): Record<string, string> => ({
  'X-Forwarded-For': ip,
});
