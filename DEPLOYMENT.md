# Deployment — TaisarTownHub (Vercel)

## Build
npm install
npm run lint
npm run test
npm run build

## Vercel
- Framework: Vite
- Build command: npm run build
- Output directory: dist
- Env vars: set VITE_DEMO_MODE=true for demo deployments.

## Notes
- No backend configured; demo mode uses localStorage. For production, configure Firebase per FIREBASE_SETUP.md.
- Native lightningcss binary is optional; cssMinify uses esbuild.
