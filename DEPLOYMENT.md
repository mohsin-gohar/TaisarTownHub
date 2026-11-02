# Deployment Guide — TaisarTownHub

## 1. Local Pre-Deployment Verification

Always run the full verification pipeline before deploying:

```bash
npm install
npm run verify
```

`npm run verify` executes all four gates:
1. `npm run lint` — TypeScript check (`tsc --noEmit`) under strict mode.
2. `npm run test` — All 59 unit tests across 9 test suites.
3. `npm run build` — Production Vite build.
4. `npm run check:bundle` — Scans public assets to ensure zero private ERP data or demo credentials leak.

---

## 2. Application Modes (`VITE_APP_MODE`)

The application supports three build modes via `src/config/appMode.ts`:

| Mode | Environment Variable | Capabilities & Security |
| :--- | :--- | :--- |
| **`public`** (Default) | *(None or `VITE_APP_MODE=public`)* | Marketing website, public listings, project showcase, file verification lookup, contact forms, and portal login/registration. **No demo role switcher, no fictional credentials exposed.** Safe fallback if env vars are unset. |
| **`demo`** | `VITE_APP_MODE=demo` or `VITE_DEMO_MODE=true` | Client presentation build with device frames (desktop, tablet, mobile), theme switcher, PIN-gated role switcher (`4545`), and seeded fictional ERP records. |
| **`production`** | `VITE_APP_MODE=production` | Real production ERP environment. Demo role switching and instant demo logins are completely disabled. Requires Firebase Authentication & Firestore configuration. |

---

## 3. Vercel Deployment Settings

- **Framework Preset:** Vite
- **Build Command:** `npm run build` (or `npm run verify` for CI gating)
- **Output Directory:** `dist`
- **Node.js Version:** `>= 20.x`

### Environment Variables on Vercel:

For a **Client Presentation / Demo Deployment**:
```ini
VITE_APP_MODE=demo
VITE_DEMO_PIN=4545
```

For a **Public Agency Showcase Deployment**:
```ini
VITE_APP_MODE=public
```
*(Or leave empty — unconfigured deployments safely default to `public` mode)*.

---

## 4. Production Backend Setup

For production client data storage, configure Firebase Authentication, Firestore, and Storage according to `FIREBASE_SETUP.md`. 
No secrets are committed in the repository.
