/**
 * Central SEO configuration — the SINGLE source of truth for every public
 * route's metadata, the canonical site origin and the JSON-LD structured data.
 *
 * Two consumers depend on this module:
 *   1. src/App.tsx            — runtime <head> sync during client-side routing
 *   2. scripts/prerender.mts  — build-time static HTML + sitemap.xml generation
 *
 * Keeping both on one module guarantees the prerendered HTML that crawlers see
 * and the live SPA can never disagree about a page's title or description.
 *
 * Business details below mirror the values configured in src/data/publicSeed.ts
 * (CMSConfig) so no structured data is invented.
 */

/**
 * Canonical site origin — single source for canonical URLs, Open Graph, JSON-LD
 * and sitemap.xml.
 *
 * Resolution order: `VITE_SITE_URL` (Vite build-time) → `VITE_SITE_URL` (Node,
 * used by scripts/prerender.mts) → production domain. The hard production
 * fallback means a missing variable can never emit localhost/vercel URLs into
 * canonical tags or the sitemap.
 */
const resolveSiteUrl = (): string => {
  const fromVite =
    typeof import.meta !== 'undefined' && import.meta.env
      ? import.meta.env.VITE_SITE_URL
      : undefined;
  const fromNode =
    typeof process !== 'undefined' && process.env ? process.env.VITE_SITE_URL : undefined;
  return (fromVite || fromNode || 'https://taisartownhub.com').replace(/\/+$/, '');
};

export const SITE_URL = resolveSiteUrl();
export const SITE_NAME = 'TaisarTownHub';
export const SITE_LOCALE = 'en_PK';
export const OG_IMAGE_PATH = '/og-image.jpg';
export const OG_IMAGE = `${SITE_URL}${OG_IMAGE_PATH}`;
export const OG_IMAGE_WIDTH = '1200';
export const OG_IMAGE_HEIGHT = '630';
export const LOGO_URL = `${SITE_URL}/logo.jpg`;

/** Verified business facts (keep in sync with CMSConfig in publicSeed.ts). */
export const BUSINESS = {
  name: 'TaisarTownHub',
  alternateName: 'Taisar Town Hub',
  description:
    'Independent real estate brokerage and property registry for Taiser Town (MDA Scheme 45), Karachi — verified plots, villas, commercial files and transparent transfer support.',
  telephone: '+923218292831',
  telephoneDisplay: '+92 321 8292831',
  email: 'contact@taisartownhub.com',
  streetAddress: 'Executive Tower 4B, Main Northern Bypass Road, Scheme 45 Entrance',
  addressLocality: 'Karachi',
  addressRegion: 'Sindh',
  addressCountry: 'PK',
  // Rounded to ~0.1 m — full float precision would be mangled by IEEE-754
  // serialisation when the JSON-LD is stringified.
  latitude: 25.029872,
  longitude: 67.042398,
  openingDays: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
  opens: '10:00',
  closes: '20:00',
} as const;

export interface RouteSeo {
  /** view key used by the client-side router (AppContext VIEW_MAP) */
  view: string;
  /** canonical, crawlable path */
  path: string;
  title: string;
  description: string;
  keywords: string;
  /** false → <meta name="robots" content="noindex,follow"> */
  indexable: boolean;
  /** include this URL in the generated sitemap.xml */
  inSitemap: boolean;
  changefreq?: 'daily' | 'weekly' | 'monthly';
  priority?: number;
  /** label trail rendered as BreadcrumbList JSON-LD (after "Home") */
  breadcrumb: string[];
  /** true when the page is only meaningful with a ?property= / ?project= id */
  requiresQuery?: boolean;
}

export const SEO_ROUTES: RouteSeo[] = [
  {
    view: 'home',
    path: '/',
    title: 'Taiser Town Scheme 45 Plots & Projects | TaisarTownHub',
    description:
      'Buy verified plots, villas and commercial files in Taiser Town (MDA Scheme 45), Karachi with transparent file verification, transfer support and site visits.',
    keywords: 'Taiser Town Scheme 45, Karachi plots, MDA Scheme 45, verified plots Karachi, property registry',
    indexable: true,
    inSitemap: true,
    changefreq: 'weekly',
    priority: 1.0,
    breadcrumb: [],
  },
  {
    view: 'properties',
    path: '/properties',
    title: 'Plots for Sale in Taiser Town Scheme 45 | TaisarTownHub',
    description:
      'Browse 80, 120, 240 and 400 Sq. Yd residential plots, villas and commercial units in Taiser Town Scheme 45. Filter by sector, size and budget.',
    keywords: 'plots for sale Scheme 45, 120 sq yd plot Karachi, commercial plot Taiser Town, villa Scheme 45',
    indexable: true,
    inSitemap: true,
    changefreq: 'daily',
    priority: 0.9,
    breadcrumb: ['Properties'],
  },
  {
    view: 'projects',
    path: '/projects',
    title: 'Housing Projects in Taiser Town Scheme 45 | TaisarTownHub',
    description:
      'Explore master-planned residential and commercial projects across every sector of Taiser Town Scheme 45, Karachi — plot sizes, pricing and booking status.',
    keywords: 'housing projects Scheme 45, new projects Karachi, Taiser Town development, MDA approved projects',
    indexable: true,
    inSitemap: true,
    changefreq: 'weekly',
    priority: 0.8,
    breadcrumb: ['Projects'],
  },
  {
    view: 'property-detail',
    path: '/properties',
    title: 'Property Details — Taiser Town Scheme 45 | TaisarTownHub',
    description:
      'Full property details: gallery, verified file status, installment planner and direct advisory contact for listings in Taiser Town Scheme 45, Karachi.',
    keywords: 'plot details Scheme 45, property file verification, installment plan Karachi plot',
    indexable: true,
    inSitemap: false,
    breadcrumb: ['Properties', 'Property Details'],
    requiresQuery: true,
  },
  {
    view: 'project-detail',
    path: '/projects',
    title: 'Project Details — Taiser Town Scheme 45 | TaisarTownHub',
    description:
      'Complete project details for Taiser Town Scheme 45 developments: plot sizes, amenities, pricing, availability and booking process.',
    keywords: 'project details Scheme 45, plot sizes, amenities, booking status Karachi',
    indexable: true,
    inSitemap: false,
    breadcrumb: ['Projects', 'Project Details'],
    requiresQuery: true,
  },
  {
    view: 'agencies',
    path: '/agencies',
    title: 'Verified Real Estate Agencies | TaisarTownHub',
    description:
      'Browse verified real estate agencies on TaisarTownHub, explore their public listings, and connect with trusted teams for buying or renting properties.',
    keywords: 'real estate agencies, verified agencies, property listings, TaisarTownHub',
    indexable: true,
    inSitemap: true,
    changefreq: 'weekly',
    priority: 0.8,
    breadcrumb: ['Agencies'],
  },
  {
    view: 'agency-profile',
    path: '/agencies',
    title: 'Agency Profile | TaisarTownHub',
    description: 'View an agency profile and its approved public property listings on TaisarTownHub.',
    keywords: 'agency profile, property listings, real estate agency',
    indexable: false,
    inSitemap: false,
    priority: 0.4,
    breadcrumb: ['Agencies', 'Agency Profile'],
  },
  {
    view: 'guide',
    path: '/guide',
    title: 'Scheme 45 Master Plan & Area Guide | TaisarTownHub',
    description:
      'Interactive sector map, street-width and utility guide, price trends and development overview for MDA Taiser Town Scheme 45, Karachi.',
    keywords: 'Scheme 45 master plan, Taiser Town map, sector guide, price trends Karachi',
    indexable: true,
    inSitemap: true,
    changefreq: 'monthly',
    priority: 0.7,
    breadcrumb: ['Scheme 45 Guide'],
  },
  {
    view: 'gallery',
    path: '/gallery',
    title: 'Site Photos & Media Gallery — Scheme 45 | TaisarTownHub',
    description:
      'Drone footage, site photographs and development updates from Taiser Town Scheme 45, Karachi.',
    keywords: 'Taiser Town photos, Scheme 45 drone footage, development updates',
    indexable: true,
    inSitemap: true,
    changefreq: 'weekly',
    priority: 0.6,
    breadcrumb: ['Media Gallery'],
  },
  {
    view: 'services',
    path: '/services',
    title: 'File Verification & Transfer Services | TaisarTownHub',
    description:
      'File verification, ownership transfer, escrow coordination, possession support and investment advisory for Taiser Town Scheme 45.',
    keywords: 'file verification Karachi, plot transfer service, escrow coordination, property advisory',
    indexable: true,
    inSitemap: true,
    changefreq: 'monthly',
    priority: 0.6,
    breadcrumb: ['Services'],
  },
  {
    view: 'about',
    path: '/about',
    title: 'About TaisarTownHub — Taiser Town Specialists',
    description:
      'Meet the principal brokers behind TaisarTownHub — dedicated Taiser Town Scheme 45 specialists with deep sector-by-sector Karachi market knowledge.',
    keywords: 'TaisarTownHub about, real estate brokers Karachi, Scheme 45 specialists',
    indexable: true,
    inSitemap: true,
    changefreq: 'monthly',
    priority: 0.5,
    breadcrumb: ['About Us'],
  },
  {
    view: 'contact',
    path: '/contact',
    title: 'Contact TaisarTownHub — Scheme 45 Office Karachi',
    description:
      'Visit our Scheme 45 office, call +92 321 8292831 or send an inquiry for verified plots and site visits in Taiser Town, Karachi.',
    keywords: 'contact real estate Scheme 45, Taiser Town office, property inquiry Karachi',
    indexable: true,
    inSitemap: true,
    changefreq: 'monthly',
    priority: 0.8,
    breadcrumb: ['Contact Us'],
  },
  {
    view: 'login',
    path: '/login',
    title: 'Dealer & Client Portal Sign In | TaisarTownHub',
    description: 'Secure portal access for authorised dealers, consultants and clients of TaisarTownHub.',
    keywords: 'portal sign in, dealer login',
    indexable: false,
    inSitemap: false,
    breadcrumb: ['Portal Sign In'],
  },
  {
    view: 'register',
    path: '/register',
    title: 'Dealer Registration — TaisarTownHub Network',
    description: 'Join the authorised real estate consultant and dealer network for Taiser Town Scheme 45.',
    keywords: 'dealer registration, become a consultant',
    indexable: false,
    inSitemap: false,
    breadcrumb: ['Dealer Registration'],
  },
  {
    view: 'admin-login',
    path: '/admin-login',
    title: 'Agency Portal Access | TaisarTownHub',
    description: 'Back-office operations, financial ledger and Scheme 45 registry access.',
    keywords: 'agency portal',
    indexable: false,
    inSitemap: false,
    breadcrumb: ['Agency Portal'],
  },
  {
    view: 'admin',
    path: '/admin',
    title: 'Back-Office — TaisarTownHub',
    description: 'Internal back-office workspace. Not a public page.',
    keywords: '',
    indexable: false,
    inSitemap: false,
    breadcrumb: [],
  },
  // G-14: cold-load auth-flow pages. Rendered by the App.tsx bridge outside
  // the SPA navigation state; intentionally noindex and never in the sitemap
  // (their URLs are personal, tokenised links from transactional mail).
  {
    view: 'reset-password',
    path: '/reset-password',
    title: 'Set a New Password | TaisarTownHub',
    description: 'Choose a new password for your TaisarTownHub account using the secure link from your email.',
    keywords: '',
    indexable: false,
    inSitemap: false,
    breadcrumb: ['Password Reset'],
  },
  {
    view: 'verify-email',
    path: '/verify-email',
    title: 'Verify Your Email | TaisarTownHub',
    description: 'Confirm your email address for your TaisarTownHub account.',
    keywords: '',
    indexable: false,
    inSitemap: false,
    breadcrumb: ['Email Verification'],
  },
];

const HOME_SEO = SEO_ROUTES[0];

/** Resolve the SEO record for a router view key, falling back to the homepage. */
export const routeSeoForView = (view: string): RouteSeo =>
  SEO_ROUTES.find((r) => r.view === view) || HOME_SEO;

/** Build an absolute canonical URL for a route, preserving detail query ids. */
export const canonicalUrl = (route: RouteSeo, query = ''): string =>
  `${SITE_URL}${route.path === '/' ? '/' : route.path}${query}`;

/** Directive used for both the static HTML and the runtime <head> sync. */
export const robotsContent = (route: RouteSeo): string =>
  route.indexable
    ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
    : 'noindex, follow';

export type JsonLd = Record<string, unknown>;

/** Primary entity: the brokerage itself (no fabricated ratings or price ranges). */
export const organisationJsonLd = (): JsonLd => ({
  '@context': 'https://schema.org',
  '@type': 'RealEstateAgent',
  '@id': `${SITE_URL}/#organization`,
  name: BUSINESS.name,
  alternateName: BUSINESS.alternateName,
  url: `${SITE_URL}/`,
  logo: LOGO_URL,
  image: OG_IMAGE,
  description: BUSINESS.description,
  telephone: BUSINESS.telephone,
  email: BUSINESS.email,
  address: {
    '@type': 'PostalAddress',
    streetAddress: BUSINESS.streetAddress,
    addressLocality: BUSINESS.addressLocality,
    addressRegion: BUSINESS.addressRegion,
    addressCountry: BUSINESS.addressCountry,
  },
  geo: {
    '@type': 'GeoCoordinates',
    latitude: BUSINESS.latitude,
    longitude: BUSINESS.longitude,
  },
  areaServed: [
    { '@type': 'Place', name: 'Taiser Town Scheme 45, Karachi, Pakistan' },
    { '@type': 'City', name: 'Karachi' },
  ],
  knowsLanguage: ['en', 'ur'],
  currenciesAccepted: 'PKR',
  openingHoursSpecification: [
    {
      '@type': 'OpeningHoursSpecification',
      dayOfWeek: [...BUSINESS.openingDays],
      opens: BUSINESS.opens,
      closes: BUSINESS.closes,
    },
  ],
});

export const websiteJsonLd = (): JsonLd => ({
  '@context': 'https://schema.org',
  '@type': 'WebSite',
  '@id': `${SITE_URL}/#website`,
  url: `${SITE_URL}/`,
  name: SITE_NAME,
  inLanguage: 'en-PK',
  publisher: { '@id': `${SITE_URL}/#organization` },
});

export const breadcrumbJsonLd = (route: RouteSeo): JsonLd => ({
  '@context': 'https://schema.org',
  '@type': 'BreadcrumbList',
  itemListElement: [
    { '@type': 'ListItem', position: 1, name: 'Home', item: `${SITE_URL}/` },
    ...route.breadcrumb.map((name, i) => ({
      '@type': 'ListItem',
      position: i + 2,
      name,
      item: i === route.breadcrumb.length - 1 ? canonicalUrl(route) : SITE_URL + route.path,
    })),
  ],
});

// ---------------------------------------------------------------------------
// Static HTML head generation (used by scripts/prerender.mts at build time)
// ---------------------------------------------------------------------------

const escapeAttr = (value: string): string =>
  value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const scriptTag = (json: JsonLd, id?: string): string =>
  `<script type="application/ld+json"${id ? ` id="${id}"` : ''}>${JSON.stringify(json)}</script>`;

/**
 * Renders the full crawlable <head> block for one route. The output is what
 * gets baked into dist/<route>.html so link unfurlers (WhatsApp, Facebook,
 * LinkedIn, Slack) and non-JS crawlers receive correct per-page metadata.
 */
export const renderSeoHead = (route: RouteSeo): string => {
  const url = canonicalUrl(route);
  const title = escapeAttr(route.title);
  const description = escapeAttr(route.description);
  return [
    `<title>${title}</title>`,
    `<meta name="description" content="${description}" />`,
    route.keywords ? `<meta name="keywords" content="${escapeAttr(route.keywords)}" />` : '',
    `<meta name="robots" content="${robotsContent(route)}" />`,
    `<link rel="canonical" href="${url}" />`,
    `<meta property="og:type" content="website" />`,
    `<meta property="og:site_name" content="${SITE_NAME}" />`,
    `<meta property="og:locale" content="${SITE_LOCALE}" />`,
    `<meta property="og:title" content="${title}" />`,
    `<meta property="og:description" content="${description}" />`,
    `<meta property="og:url" content="${url}" />`,
    `<meta property="og:image" content="${OG_IMAGE}" />`,
    `<meta property="og:image:width" content="${OG_IMAGE_WIDTH}" />`,
    `<meta property="og:image:height" content="${OG_IMAGE_HEIGHT}" />`,
    `<meta property="og:image:alt" content="${SITE_NAME} — verified plots in Taiser Town Scheme 45, Karachi" />`,
    `<meta name="twitter:card" content="summary_large_image" />`,
    `<meta name="twitter:title" content="${title}" />`,
    `<meta name="twitter:description" content="${description}" />`,
    `<meta name="twitter:image" content="${OG_IMAGE}" />`,
    scriptTag(organisationJsonLd()),
    scriptTag(websiteJsonLd()),
    route.breadcrumb.length ? scriptTag(breadcrumbJsonLd(route), 'route-breadcrumb-jsonld') : '',
  ]
    .filter(Boolean)
    .join('\n    ');
};

/** Marker comments in index.html that delimit the region prerender.mts rewrites. */
export const SEO_HEAD_START = '<!-- seo:head:start -->';
export const SEO_HEAD_END = '<!-- seo:head:end -->';
