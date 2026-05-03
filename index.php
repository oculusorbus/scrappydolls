<?php
require_once __DIR__ . '/lib/bootstrap.php';

// Pull a pool of dolls in priority order (featured-available > available > sold).
// Carousel uses 12, roster uses 9 — drawn from one query so featured/available
// dolls take precedence in both surfaces.
$_pool = landing_dolls(24);

// Distribute across two surfaces. If there aren't enough unique dolls for
// non-overlapping sets, allow overlap so neither surface is empty.
if (count($_pool) >= 21) {
    $carouselDolls = array_slice($_pool, 0, 12);
    $rosterDolls   = array_slice($_pool, 12, 9);
} else {
    $carouselDolls = array_slice($_pool, 0, min(12, count($_pool)));
    $rosterDolls   = array_slice($_pool, 0, min(9,  count($_pool)));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scrappy Dolls — Handmade Cloth Dolls &amp; Memory Dolls by Kanda Kay</title>
  <meta name="description" content="Scrappy Dolls by artist Kanda Kay — one-of-a-kind handmade cloth dolls and custom memory dolls stitched from quilting cottons, vintage prints, and fabric remnants. OOAK art dolls, folk art tradition, and the scrappy doll community.">
  <meta name="author" content="Kanda Kay">
  <meta name="keywords" content="scrappy dolls, handmade cloth dolls, memory dolls, custom dolls, rag dolls, OOAK art dolls, one of a kind dolls, fabric dolls, folk art dolls, scrap fabric dolls, quilting cotton dolls, artisan dolls, handmade doll artist, cloth doll artist, keepsake dolls, heirloom dolls, doll commissions, Kanda Kay, scrappy doll community, wonky dolls, textile art dolls">
  <link rel="canonical" href="https://scrappydolls.com/">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <meta name="theme-color" content="#b13e54">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">
  <link rel="apple-touch-icon" href="/favicon-192.png">

  <!-- Preload hero image for LCP -->
  <link rel="preload" as="image" href="/images/doll-rainbow-hair.jpg" fetchpriority="high">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:locale" content="en_US">
  <meta property="og:title" content="Scrappy Dolls — Handmade Cloth &amp; Memory Dolls by Kanda Kay">
  <meta property="og:description" content="Handmade cloth dolls and custom memory dolls by artist Kanda Kay — one-of-a-kind keepsakes stitched from quilting cottons, vintage prints, and fabric remnants.">
  <meta property="og:url" content="https://scrappydolls.com/">
  <meta property="og:site_name" content="Scrappy Dolls">
  <meta property="og:image" content="https://scrappydolls.com/images/og-image.jpg">
  <meta property="og:image:secure_url" content="https://scrappydolls.com/images/og-image.jpg">
  <meta property="og:image:type" content="image/jpeg">
  <meta property="og:image:width" content="1008">
  <meta property="og:image:height" content="560">
  <meta property="og:image:alt" content="A handmade Scrappy Doll by Kanda Kay — burlap face with embroidered features and yarn hair">

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Scrappy Dolls — Handmade Cloth &amp; Memory Dolls by Kanda Kay">
  <meta name="twitter:description" content="Handmade cloth dolls and custom memory dolls by artist Kanda Kay — one-of-a-kind keepsakes stitched from beloved fabric.">
  <meta name="twitter:image" content="https://scrappydolls.com/images/og-image.jpg">
  <meta name="twitter:image:alt" content="A handmade Scrappy Doll by Kanda Kay">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..900,0..100;1,9..144,300..900,0..100&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "LocalBusiness",
        "@id": "https://scrappydolls.com/#business",
        "name": "Scrappy Dolls",
        "alternateName": "Scrappy Dolls by Kanda Kay",
        "description": "Handmade cloth dolls and custom memory dolls by artist Kanda Kay, based in San Antonio, Texas. One-of-a-kind keepsakes stitched from quilting cottons, vintage prints, and fabric remnants.",
        "slogan": "Cloth dolls, stitched one at a time.",
        "url": "https://scrappydolls.com/",
        "image": "https://scrappydolls.com/images/og-image.jpg",
        "logo": "https://scrappydolls.com/favicon-192.png",
        "founder": { "@id": "https://scrappydolls.com/#kanda" },
        "address": {
          "@type": "PostalAddress",
          "addressLocality": "San Antonio",
          "addressRegion": "TX",
          "addressCountry": "US"
        },
        "geo": {
          "@type": "GeoCoordinates",
          "latitude": 29.4252,
          "longitude": -98.4946
        },
        "areaServed": [
          { "@type": "City", "name": "San Antonio" },
          { "@type": "State", "name": "Texas" },
          { "@type": "Country", "name": "United States" }
        ],
        "knowsAbout": [
          "Handmade cloth dolls",
          "Memory dolls",
          "Custom doll commissions",
          "Keepsake dolls",
          "Fabric arts",
          "Quilting"
        ],
        "makesOffer": [
          {
            "@type": "Offer",
            "name": "Custom Memory Dolls",
            "description": "One-of-a-kind cloth dolls made from a customer's own meaningful fabric — outgrown clothing, a wedding dress, a beloved quilt.",
            "category": "Memory dolls",
            "availability": "https://schema.org/InStock",
            "seller": { "@id": "https://scrappydolls.com/#business" }
          },
          {
            "@type": "Offer",
            "name": "Original Scrappy Dolls",
            "description": "Original one-of-a-kind handmade cloth dolls stitched from quilting cottons, vintage prints, and fabric remnants.",
            "category": "Cloth dolls",
            "availability": "https://schema.org/LimitedAvailability",
            "seller": { "@id": "https://scrappydolls.com/#business" }
          }
        ],
        "sameAs": ["https://www.facebook.com/kandakayartist/"]
      },
      {
        "@type": "Person",
        "@id": "https://scrappydolls.com/#kanda",
        "name": "Kanda Kay",
        "jobTitle": "Doll Artist",
        "description": "Artist behind Scrappy Dolls, based in San Antonio, Texas. Each doll is hand-cut and hand-stitched from quilting cottons, vintage prints, and beloved fabric remnants.",
        "url": "https://scrappydolls.com/",
        "homeLocation": {
          "@type": "Place",
          "address": {
            "@type": "PostalAddress",
            "addressLocality": "San Antonio",
            "addressRegion": "TX",
            "addressCountry": "US"
          }
        },
        "worksFor": { "@id": "https://scrappydolls.com/#business" },
        "sameAs": ["https://www.facebook.com/kandakayartist/"]
      },
      {
        "@type": "WebSite",
        "@id": "https://scrappydolls.com/#website",
        "name": "Scrappy Dolls",
        "url": "https://scrappydolls.com/",
        "inLanguage": "en-US",
        "publisher": { "@id": "https://scrappydolls.com/#business" }
      },
      {
        "@type": "WebPage",
        "@id": "https://scrappydolls.com/#webpage",
        "url": "https://scrappydolls.com/",
        "name": "Scrappy Dolls — Handmade Cloth & Memory Dolls by Kanda Kay",
        "description": "Handmade cloth dolls and custom memory dolls by artist Kanda Kay. One-of-a-kind keepsakes stitched from quilting cottons, vintage prints, and fabric remnants.",
        "inLanguage": "en-US",
        "isPartOf": { "@id": "https://scrappydolls.com/#website" },
        "about": { "@id": "https://scrappydolls.com/#business" },
        "primaryImageOfPage": { "@id": "https://scrappydolls.com/#hero-image" }
      },
      {
        "@type": "ImageObject",
        "@id": "https://scrappydolls.com/#hero-image",
        "url": "https://scrappydolls.com/images/doll-rainbow-hair.jpg",
        "caption": "Handmade Scrappy Doll by Kanda Kay with multicolored yarn hair, embroidered features, and a vibrant patchwork dress with lace trim.",
        "creditText": "Kanda Kay",
        "creator": { "@id": "https://scrappydolls.com/#kanda" }
      }
    ]
  }
  </script>

  <style>
    :root {
      /* Surfaces */
      --paper:    #faf3ee;
      --paper-2:  #f0e5da;
      --paper-3:  #e9dccd;

      /* Type */
      --ink:        #1a1318;
      --ink-soft:   #5a4a52;
      --ink-muted:  #8a7780;

      /* Accents */
      --rose:       #b13e54;
      --rose-dark:  #8b2d40;
      --rose-light: #e8a3b0;
      --sage:       #7a8568;
      --gold:       #c9a567;
      --thread:     #d9b382;

      /* Lines & shadows */
      --rule: #e2cfbe;
      --rule-soft: #ede0d2;
      --shadow-sm: 0 1px 2px rgba(26, 19, 24, 0.04);
      --shadow-md: 0 12px 32px rgba(139, 45, 64, 0.10);
      --shadow-lg: 0 30px 80px rgba(26, 19, 24, 0.14);

      /* Typography */
      --font-display: "Fraunces", "Iowan Old Style", "Palatino Linotype", Georgia, serif;
      --font-sans:    "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;

      /* Layout */
      --max:    74rem;
      --max-narrow: 52rem;
      --radius: 18px;
      --radius-lg: 28px;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0;
      font-family: var(--font-sans);
      color: var(--ink);
      background: var(--paper);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      text-rendering: optimizeLegibility;
      overflow-x: hidden;
    }

    a {
      color: var(--rose-dark);
      text-decoration-thickness: 1px;
      text-underline-offset: 3px;
    }
    a:hover { color: var(--rose); }

    img { max-width: 100%; height: auto; display: block; }

    /* === Display type === */
    .h-display {
      font-family: var(--font-display);
      font-weight: 400;
      font-variation-settings: "opsz" 144, "SOFT" 100;
      letter-spacing: -0.02em;
      line-height: 1.02;
      color: var(--ink);
      margin: 0;
    }
    h1.h-display { font-size: clamp(2.75rem, 7vw, 5.5rem); }
    h2.h-display { font-size: clamp(2rem, 4.5vw, 3.25rem); }

    h3 {
      font-family: var(--font-display);
      font-weight: 500;
      font-variation-settings: "opsz" 36, "SOFT" 80;
      font-size: 1.35rem;
      letter-spacing: -0.01em;
      margin: 0 0 0.4rem;
      line-height: 1.2;
    }

    p { margin: 0 0 1em; }

    .eyebrow {
      font-family: var(--font-sans);
      font-size: 0.78rem;
      font-weight: 600;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--rose);
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      margin: 0 0 1.25rem;
    }
    .eyebrow::before {
      content: "";
      width: 1.75rem;
      height: 1px;
      background: currentColor;
      display: inline-block;
    }

    .lede {
      font-family: var(--font-display);
      font-weight: 350;
      font-variation-settings: "opsz" 36, "SOFT" 100;
      font-size: clamp(1.15rem, 1.6vw, 1.45rem);
      line-height: 1.45;
      color: var(--ink-soft);
      max-width: 38rem;
    }

    .wrap { max-width: var(--max); margin: 0 auto; padding: 0 1.5rem; }
    .wrap-narrow { max-width: var(--max-narrow); margin: 0 auto; padding: 0 1.5rem; }

    /* === Header === */
    header.site {
      position: sticky;
      top: 0;
      z-index: 50;
      backdrop-filter: saturate(180%) blur(14px);
      -webkit-backdrop-filter: saturate(180%) blur(14px);
      background: color-mix(in oklab, var(--paper) 82%, transparent);
      border-bottom: 1px solid color-mix(in oklab, var(--rule) 50%, transparent);
    }
    header.site .wrap {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding-top: 1rem;
      padding-bottom: 1rem;
    }
    .brand {
      font-family: var(--font-display);
      color: var(--ink);
      display: inline-flex;
      flex-direction: column;
      line-height: 1;
      gap: 0.15rem;
    }
    .brand-name {
      font-weight: 600;
      font-variation-settings: "opsz" 72, "SOFT" 80;
      font-size: 1.95rem;
      letter-spacing: -0.025em;
      line-height: 0.95;
      color: inherit;
      text-decoration: none;
      transition: color 0.2s ease;
    }
    .brand-name:hover { color: var(--rose-dark); }
    .brand-name em {
      font-style: italic;
      color: var(--rose);
      font-weight: 400;
    }
    .brand-attribution {
      display: inline-flex;
      flex-direction: column;
      gap: 0.05rem;
      margin-top: 0.15rem;
      text-decoration: none;
      color: inherit;
    }
    .brand-attribution .brand-by,
    .brand-attribution .brand-artist {
      transition: color 0.2s ease;
    }
    .brand-attribution:hover .brand-by,
    .brand-attribution:hover .brand-artist {
      color: var(--rose);
    }
    .brand-by {
      font-style: italic;
      font-variation-settings: "opsz" 36, "SOFT" 100;
      font-weight: 400;
      font-size: 0.86rem;
      color: var(--ink-soft);
      letter-spacing: 0.05em;
    }
    .brand-artist {
      font-family: var(--font-sans);
      font-size: 0.62rem;
      letter-spacing: 0.27em;
      text-transform: uppercase;
      color: var(--ink-muted);
      font-weight: 600;
    }
    nav.primary {
      display: flex;
      align-items: center;
      gap: 1.5rem;
    }
    nav.primary a {
      color: var(--ink-soft);
      text-decoration: none;
      font-size: 0.92rem;
      font-weight: 500;
      transition: color 0.2s ease;
    }
    nav.primary a:hover { color: var(--ink); }
    nav.primary .btn-mini {
      background: var(--ink);
      color: var(--paper);
      padding: 0.55rem 1.1rem;
      border-radius: 999px;
      font-weight: 500;
      font-size: 0.88rem;
      transition: background 0.2s ease;
    }
    nav.primary .btn-mini:hover { background: var(--rose-dark); color: #fff; }
    @media (max-width: 40rem) {
      nav.primary { gap: 0.9rem; }
      nav.primary a:not(.btn-mini):not(.priority) { display: none; }
      nav.primary a.priority {
        font-weight: 600;
        color: var(--ink);
      }
    }

    /* === Buttons === */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.95rem 1.5rem;
      border-radius: 999px;
      font-family: var(--font-sans);
      font-weight: 600;
      text-decoration: none;
      font-size: 0.95rem;
      transition: transform 0.15s ease, background 0.2s ease, box-shadow 0.25s ease, color 0.2s ease;
      cursor: pointer;
      border: 1px solid transparent;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary {
      background: var(--ink);
      color: var(--paper);
      box-shadow: var(--shadow-sm);
    }
    .btn-primary:hover {
      background: var(--rose-dark);
      color: #fff;
      box-shadow: var(--shadow-md);
    }
    .btn-ghost {
      background: transparent;
      color: var(--ink);
      border-color: var(--ink);
    }
    .btn-ghost:hover {
      background: var(--ink);
      color: var(--paper);
    }
    .btn .arrow {
      display: inline-block;
      transition: transform 0.25s ease;
    }
    .btn:hover .arrow { transform: translateX(3px); }

    .cta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-top: 2rem;
    }

    /* === Generic section === */
    section { padding: 6rem 0; position: relative; }
    section.alt { background: var(--paper-2); }
    section.dark { background: var(--ink); color: var(--paper); }
    section.dark .lede,
    section.dark p { color: color-mix(in oklab, var(--paper) 75%, transparent); }
    section.dark h2.h-display,
    section.dark h3 { color: var(--paper); }

    /* Stitched dashed divider */
    .stitched {
      width: 100%;
      height: 1px;
      background-image: linear-gradient(to right, var(--rule) 50%, transparent 50%);
      background-size: 12px 1px;
      background-repeat: repeat-x;
      border: none;
      margin: 0;
    }

    /* === HERO === */
    .hero {
      padding: clamp(4rem, 9vw, 8rem) 0 clamp(4rem, 8vw, 7rem);
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 60% 40% at 85% 10%, color-mix(in oklab, var(--rose-light) 60%, transparent) 0%, transparent 70%),
        radial-gradient(ellipse 50% 50% at 5% 95%, color-mix(in oklab, var(--thread) 35%, transparent) 0%, transparent 65%),
        var(--paper);
      z-index: -1;
    }
    .hero-grid {
      display: grid;
      gap: clamp(2rem, 5vw, 4rem);
      grid-template-columns: 1.25fr 1fr;
      align-items: center;
    }
    @media (max-width: 52rem) {
      .hero-grid { grid-template-columns: 1fr; }
    }
    .hero-image-stack {
      position: relative;
      aspect-ratio: 4 / 5;
    }
    .hero-image-stack .frame {
      position: absolute;
      inset: 0;
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-lg);
      transform: rotate(-1.5deg);
      transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    .hero-image-stack:hover .frame { transform: rotate(-0.5deg) scale(1.01); }
    .hero-image-stack .frame img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
    }
    .hero-image-stack .badge {
      position: absolute;
      bottom: -1.5rem;
      left: -1.5rem;
      background: var(--paper);
      border: 1px solid var(--rule);
      border-radius: 999px;
      padding: 0.7rem 1.25rem;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--ink);
      letter-spacing: 0.06em;
      text-transform: uppercase;
      box-shadow: var(--shadow-md);
      transform: rotate(-3deg);
    }
    .hero-image-stack .badge em {
      color: var(--rose);
      font-style: normal;
      font-weight: 700;
    }
    @media (max-width: 52rem) {
      .hero-image-stack {
        max-width: 24rem;
        margin: 1rem auto 2.5rem;
        /* On mobile the row collapses if children are all absolute-positioned —
           let the image flow normally so the box has real height. */
        aspect-ratio: auto;
      }
      .hero-image-stack .frame {
        position: relative;
        inset: auto;
      }
      .hero-image-stack .frame img {
        width: 100%;
        height: auto;
        aspect-ratio: 4 / 5;
      }
      /* Pull the badge inside the frame so overflow-x: hidden doesn't clip it */
      .hero-image-stack .badge {
        bottom: 1rem;
        left: 1rem;
      }
    }

    .hero-meta {
      display: flex;
      gap: 2rem;
      margin-top: 2.5rem;
      padding-top: 2rem;
      border-top: 1px dashed var(--rule);
      flex-wrap: wrap;
    }
    .hero-meta div { display: flex; flex-direction: column; gap: 0.15rem; }
    .hero-meta .k {
      font-family: var(--font-display);
      font-size: 1.6rem;
      font-weight: 500;
      font-variation-settings: "opsz" 36, "SOFT" 100;
      color: var(--ink);
      line-height: 1;
    }
    .hero-meta .v {
      font-size: 0.78rem;
      color: var(--ink-muted);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      font-weight: 500;
    }

    /* === ABOUT === */
    .about-grid {
      display: grid;
      gap: clamp(2.5rem, 6vw, 5rem);
      grid-template-columns: 1fr 1.1fr;
      align-items: center;
    }
    @media (max-width: 52rem) {
      .about-grid { grid-template-columns: 1fr; }
    }
    .about-portrait {
      position: relative;
      aspect-ratio: 4 / 5;
      border-radius: var(--radius-lg);
      overflow: hidden;
      background: var(--paper-3) url("images/doll-feature-horns.jpg") center top/cover no-repeat;
      box-shadow: var(--shadow-lg);
      transform: rotate(1deg);
    }
    .about-portrait::after {
      content: "";
      position: absolute;
      inset: 0.6rem;
      border: 1px dashed color-mix(in oklab, var(--paper) 70%, transparent);
      border-radius: calc(var(--radius-lg) - 8px);
      pointer-events: none;
    }
    /* === BIO === */
    .bio-grid {
      display: grid;
      gap: clamp(2.5rem, 6vw, 5rem);
      grid-template-columns: 1.05fr 1fr;
      align-items: center;
    }
    @media (max-width: 52rem) {
      .bio-grid { grid-template-columns: 1fr; }
    }
    .bio-portrait {
      position: relative;
      border-radius: var(--radius-lg);
      overflow: hidden;
      background: var(--paper-3);
      box-shadow: var(--shadow-lg);
      transform: rotate(-1deg);
    }
    .bio-portrait::after {
      content: "";
      position: absolute;
      inset: 0.6rem;
      border: 1px dashed color-mix(in oklab, var(--paper) 70%, transparent);
      border-radius: calc(var(--radius-lg) - 8px);
      pointer-events: none;
    }
    .bio-portrait img {
      display: block;
      width: 100%;
      height: auto;
    }
    @media (max-width: 52rem) {
      .bio-portrait {
        transform: rotate(0deg);
        max-width: 30rem;
        margin: 0 auto;
      }
    }

    .pull-quote {
      font-family: var(--font-display);
      font-weight: 350;
      font-variation-settings: "opsz" 144, "SOFT" 100;
      font-size: clamp(1.45rem, 2.4vw, 2rem);
      line-height: 1.3;
      color: var(--ink);
      margin: 1.5rem 0 0;
      padding-left: 1.5rem;
      border-left: 2px solid var(--rose);
    }
    .pull-quote em {
      color: var(--rose);
      font-style: italic;
    }

    /* === PROCESS === */
    .process {
      display: grid;
      gap: 2rem;
      grid-template-columns: repeat(4, 1fr);
      margin-top: 3rem;
      counter-reset: step;
    }
    @media (max-width: 60rem) {
      .process { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 32rem) {
      .process { grid-template-columns: 1fr; }
    }
    .step {
      position: relative;
      padding: 2rem 1.5rem 1.75rem;
      background: var(--paper);
      border: 1px solid var(--rule);
      border-radius: var(--radius);
      transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    }
    .step:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-md);
      border-color: var(--rose-light);
    }
    .step::before {
      counter-increment: step;
      content: "0" counter(step);
      font-family: var(--font-display);
      font-weight: 500;
      font-style: italic;
      font-variation-settings: "opsz" 36, "SOFT" 100;
      font-size: 2.25rem;
      color: var(--rose);
      display: block;
      line-height: 1;
      margin-bottom: 1rem;
    }
    .step p {
      color: var(--ink-soft);
      font-size: 0.95rem;
      margin: 0;
    }

    /* === MARQUEE === */
    .marquee {
      padding: 3.5rem 0;
      background: var(--ink);
      overflow: hidden;
      position: relative;
      border-top: 1px solid color-mix(in oklab, var(--paper) 8%, transparent);
      border-bottom: 1px solid color-mix(in oklab, var(--paper) 8%, transparent);
    }
    .marquee-track {
      display: flex;
      gap: 1.25rem;
      width: max-content;
      animation: marquee 60s linear infinite;
    }
    .marquee:hover .marquee-track { animation-play-state: paused; }
    .marquee-item {
      position: relative;
      flex-shrink: 0;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
      transition: transform 0.3s ease;
    }
    .marquee-item:hover { transform: translateY(-4px); }
    .marquee-track img {
      height: 16rem;
      width: auto;
      border-radius: var(--radius);
      object-fit: cover;
      flex-shrink: 0;
      display: block;
    }
    .marquee-sold {
      position: absolute;
      top: 0.6rem; right: 0.6rem;
      background: rgba(26, 19, 24, 0.85);
      color: var(--paper);
      font-size: 0.65rem;
      font-weight: 600;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      padding: 0.3rem 0.55rem;
      border-radius: 999px;
    }
    @keyframes marquee {
      from { transform: translateX(0); }
      to   { transform: translateX(-50%); }
    }
    @media (prefers-reduced-motion: reduce) {
      .marquee-track { animation: none; }
    }

    /* === GALLERY === */
    .gallery-head {
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 2rem;
      margin-bottom: 3rem;
      flex-wrap: wrap;
    }
    .gallery-head p {
      max-width: 28rem;
      color: var(--ink-soft);
      margin: 0;
    }
    .gallery {
      display: grid;
      gap: 1.25rem;
      grid-template-columns: repeat(6, 1fr);
      grid-auto-rows: 13rem;
    }
    .gallery figure {
      margin: 0;
      border-radius: var(--radius);
      overflow: hidden;
      background: var(--paper-3);
      position: relative;
      transition: transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.4s ease;
      cursor: zoom-in;
    }
    .gallery figure:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
    }
    .gallery figure img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
    }
    .gallery figure img[src*="doll-owl"],
    .gallery figure img[src*="doll-dog-pair"] {
      object-position: center center;
    }
    .gallery figure:hover img { transform: scale(1.04); }
    .gallery figure:focus-visible {
      outline: 2px solid var(--rose);
      outline-offset: 4px;
    }
    /* Asymmetric layout */
    .gallery figure:nth-child(1) { grid-column: span 3; grid-row: span 2; }   /* big featured */
    .gallery figure:nth-child(2) { grid-column: span 3; grid-row: span 1; }
    .gallery figure:nth-child(3) { grid-column: span 2; grid-row: span 1; }
    .gallery figure:nth-child(4) { grid-column: span 1; grid-row: span 1; }
    .gallery figure:nth-child(5) { grid-column: span 2; grid-row: span 2; }
    .gallery figure:nth-child(6) { grid-column: span 2; grid-row: span 1; }
    .gallery figure:nth-child(7) { grid-column: span 2; grid-row: span 1; }
    .gallery figure:nth-child(8) { grid-column: span 2; grid-row: span 1; }
    .gallery figure:nth-child(9) { grid-column: span 2; grid-row: span 1; }
    @media (max-width: 60rem) {
      .gallery {
        grid-template-columns: repeat(2, 1fr);
        grid-auto-rows: 11rem;
      }
      .gallery figure:nth-child(n) { grid-column: span 1; grid-row: span 1; }
      .gallery figure:nth-child(1) { grid-column: span 2; grid-row: span 2; }
    }
    @media (max-width: 30rem) {
      .gallery { grid-template-columns: 1fr; }
      .gallery figure:nth-child(n) { grid-column: span 1; grid-row: span 1; }
    }

    /* === ROSTER (live shop preview on landing) === */
    .roster {
      display: grid;
      gap: 1.25rem;
      grid-template-columns: repeat(6, 1fr);
      grid-auto-rows: 13rem;
    }
    .roster-card {
      position: relative;
      display: block;
      border-radius: var(--radius);
      overflow: hidden;
      background: var(--paper-3);
      text-decoration: none;
      color: inherit;
      transition: transform 0.3s cubic-bezier(0.2,0.8,0.2,1), box-shadow 0.3s ease;
    }
    .roster-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
      color: inherit;
    }
    .roster-card img,
    .roster-placeholder {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      transition: transform 0.6s cubic-bezier(0.2,0.8,0.2,1);
    }
    .roster-placeholder { background: var(--paper-3); }
    .roster-card:hover img { transform: scale(1.04); }
    .roster-meta {
      position: absolute;
      left: 0; right: 0; bottom: 0;
      padding: 1.5rem 1rem 0.85rem;
      background: linear-gradient(to top, rgba(26,19,24,0.85), rgba(26,19,24,0));
      color: var(--paper);
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      gap: 0.75rem;
    }
    .roster-title {
      font-family: var(--font-display);
      font-weight: 500;
      font-size: 1rem;
      line-height: 1.15;
      letter-spacing: -0.005em;
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .roster-price {
      background: rgba(255,255,255,0.95);
      color: var(--rose-dark);
      font-weight: 600;
      font-size: 0.82rem;
      padding: 0.25rem 0.55rem;
      border-radius: 999px;
      flex-shrink: 0;
    }
    .roster-tag.sold {
      background: rgba(255,255,255,0.95);
      color: var(--ink);
      font-size: 0.65rem;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      padding: 0.3rem 0.6rem;
      border-radius: 999px;
      flex-shrink: 0;
    }
    /* Asymmetric layout — same shape as the old .gallery */
    .roster .roster-card:nth-child(1) { grid-column: span 3; grid-row: span 2; }
    .roster .roster-card:nth-child(2) { grid-column: span 3; grid-row: span 1; }
    .roster .roster-card:nth-child(3) { grid-column: span 2; grid-row: span 1; }
    .roster .roster-card:nth-child(4) { grid-column: span 1; grid-row: span 1; }
    .roster .roster-card:nth-child(5) { grid-column: span 2; grid-row: span 2; }
    .roster .roster-card:nth-child(6) { grid-column: span 2; grid-row: span 1; }
    .roster .roster-card:nth-child(7) { grid-column: span 2; grid-row: span 1; }
    .roster .roster-card:nth-child(8) { grid-column: span 2; grid-row: span 1; }
    .roster .roster-card:nth-child(9) { grid-column: span 2; grid-row: span 1; }
    @media (max-width: 60rem) {
      .roster { grid-template-columns: repeat(2, 1fr); grid-auto-rows: 11rem; }
      .roster .roster-card:nth-child(n) { grid-column: span 1; grid-row: span 1; }
      .roster .roster-card:nth-child(1) { grid-column: span 2; grid-row: span 2; }
    }
    @media (max-width: 30rem) {
      .roster { grid-template-columns: 1fr; }
      .roster .roster-card:nth-child(n) { grid-column: span 1; grid-row: span 1; }
    }

    /* === LIGHTBOX === */
    .lightbox {
      border: none;
      padding: 0;
      margin: auto;
      background: transparent;
      width: max-content;
      height: max-content;
      max-width: 100vw;
      max-height: 100vh;
      overflow: visible;
      outline: none;
    }
    .lightbox::backdrop {
      background: color-mix(in oklab, var(--ink) 92%, transparent);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }
    .lightbox img {
      display: block;
      max-width: 92vw;
      max-height: 92vh;
      width: auto;
      height: auto;
      border-radius: var(--radius);
      box-shadow: var(--shadow-lg);
      animation: lightbox-in 0.25s ease-out;
    }
    @keyframes lightbox-in {
      from { opacity: 0; transform: scale(0.96); }
      to   { opacity: 1; transform: scale(1); }
    }
    .lightbox-btn {
      position: fixed;
      background: var(--paper);
      color: var(--ink);
      border: 1px solid var(--rule);
      border-radius: 999px;
      width: 3rem;
      height: 3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-family: var(--font-display);
      font-size: 1.6rem;
      line-height: 1;
      font-weight: 400;
      box-shadow: var(--shadow-md);
      transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
      padding: 0;
    }
    .lightbox-btn:hover {
      background: var(--rose-dark);
      color: #fff;
    }
    .lightbox-close { top: 1.25rem; right: 1.25rem; }
    .lightbox-prev,
    .lightbox-next { top: 50%; transform: translateY(-50%); }
    .lightbox-prev { left: 1.25rem; }
    .lightbox-next { right: 1.25rem; }
    .lightbox-prev:hover,
    .lightbox-next:hover { transform: translateY(-50%) scale(1.05); }
    .lightbox-close:hover { transform: scale(1.05); }
    @media (max-width: 40rem) {
      .lightbox-btn { width: 2.5rem; height: 2.5rem; font-size: 1.35rem; }
      .lightbox-close { top: 0.75rem; right: 0.75rem; }
      .lightbox-prev { left: 0.5rem; }
      .lightbox-next { right: 0.5rem; }
    }

    /* === TESTIMONIALS === */
    .testimonials-head {
      text-align: center;
      margin-bottom: 3rem;
    }
    .testimonials-head .eyebrow { justify-content: center; }
    .testimonials-grid {
      display: grid;
      gap: 1.75rem;
      grid-template-columns: repeat(3, 1fr);
    }
    @media (max-width: 60rem) {
      .testimonials-grid { grid-template-columns: 1fr; }
    }
    .testimonial-card {
      margin: 0;
      background: var(--paper);
      border: 1px solid var(--rule);
      border-radius: var(--radius);
      padding: 2rem 1.75rem 1.75rem;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    }
    .testimonial-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-md);
      border-color: var(--rose-light);
    }
    .testimonial-card::before {
      content: "\201C";
      font-family: var(--font-display);
      font-weight: 500;
      font-size: 3.5rem;
      line-height: 0.7;
      color: var(--rose);
      opacity: 0.5;
      display: block;
      margin-bottom: 0.75rem;
    }
    .testimonial-card blockquote {
      font-family: var(--font-display);
      font-weight: 350;
      font-variation-settings: "opsz" 36, "SOFT" 100;
      font-style: italic;
      font-size: 1.1rem;
      line-height: 1.5;
      color: var(--ink);
      margin: 0 0 1.5rem;
      flex: 1;
    }
    .testimonial-card figcaption {
      padding-top: 1rem;
      border-top: 1px dashed var(--rule);
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
    }
    .testimonial-card cite {
      font-style: normal;
      font-family: var(--font-sans);
      font-size: 0.85rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--ink);
      font-weight: 600;
    }
    .testimonial-card .meta {
      font-size: 0.72rem;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--ink-muted);
      font-weight: 500;
    }

    /* === FAQ === */
    details.faq {
      border-bottom: 1px solid var(--rule);
      padding: 1.4rem 0;
    }
    details.faq:first-of-type { border-top: 1px solid var(--rule); }
    details.faq summary {
      cursor: pointer;
      font-family: var(--font-display);
      font-weight: 500;
      font-variation-settings: "opsz" 36, "SOFT" 80;
      font-size: 1.2rem;
      list-style: none;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      color: var(--ink);
      transition: color 0.2s ease;
    }
    details.faq summary:hover { color: var(--rose); }
    details.faq summary::-webkit-details-marker { display: none; }
    details.faq summary::after {
      content: "";
      width: 1.5rem;
      height: 1.5rem;
      border-radius: 999px;
      background:
        linear-gradient(currentColor, currentColor) center/0.7rem 1px no-repeat,
        linear-gradient(currentColor, currentColor) center/1px 0.7rem no-repeat,
        color-mix(in oklab, var(--rose) 10%, transparent);
      color: var(--rose);
      transition: transform 0.3s ease, background-color 0.3s ease;
      flex-shrink: 0;
    }
    details.faq[open] summary::after {
      transform: rotate(45deg);
      background:
        linear-gradient(currentColor, currentColor) center/0.7rem 1px no-repeat,
        linear-gradient(currentColor, currentColor) center/1px 0.7rem no-repeat,
        color-mix(in oklab, var(--rose) 18%, transparent);
    }
    details.faq p {
      margin: 1rem 0 0;
      color: var(--ink-soft);
      max-width: 44rem;
    }

    /* === CTA / FOLLOW === */
    .follow-card {
      background: var(--ink);
      color: var(--paper);
      border-radius: var(--radius-lg);
      padding: clamp(2.5rem, 5vw, 4rem);
      display: grid;
      gap: 2rem;
      grid-template-columns: 1.4fr 1fr;
      align-items: center;
      position: relative;
      overflow: hidden;
    }
    .follow-card::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse 50% 60% at 90% 0%, color-mix(in oklab, var(--rose) 35%, transparent), transparent 70%);
      pointer-events: none;
    }
    .follow-card > * { position: relative; }
    .follow-card h2 {
      color: var(--paper);
      margin: 0 0 0.5rem;
    }
    .follow-card p {
      color: color-mix(in oklab, var(--paper) 75%, transparent);
      margin: 0;
      max-width: 28rem;
    }
    .follow-card .btn-primary {
      background: var(--paper);
      color: var(--ink);
      justify-self: end;
    }
    .follow-card .btn-primary:hover {
      background: var(--rose-light);
      color: var(--ink);
    }
    @media (max-width: 48rem) {
      .follow-card { grid-template-columns: 1fr; }
      .follow-card .btn-primary { justify-self: start; }
    }

    /* === FOOTER === */
    footer.site {
      padding: 4rem 0 3rem;
      background: var(--paper-2);
      color: var(--ink-soft);
      font-size: 0.9rem;
    }
    footer.site .row {
      display: grid;
      gap: 1.25rem;
      grid-template-columns: 1fr auto;
      align-items: end;
    }
    @media (max-width: 40rem) {
      footer.site .row { grid-template-columns: 1fr; }
    }
    footer.site .sig {
      font-family: var(--font-display);
      font-style: italic;
      font-variation-settings: "opsz" 144, "SOFT" 100;
      font-size: 1.5rem;
      color: var(--ink);
      margin: 0 0 0.5rem;
      line-height: 1;
    }
    footer.site a {
      color: var(--ink);
      font-weight: 500;
      text-decoration: none;
      border-bottom: 1px solid var(--rule);
      transition: border-color 0.2s ease;
    }
    footer.site a:hover { border-color: var(--rose); }
    footer.site .legal { font-size: 0.8rem; color: var(--ink-muted); }

    /* === Skip link === */
    .skip {
      position: absolute; left: -9999px; top: auto;
    }
    .skip:focus {
      position: static;
      padding: 0.5rem 1rem;
      background: var(--ink);
      color: var(--paper);
    }

    /* === Reveal-on-scroll === */
    .reveal {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.8s ease, transform 0.8s ease;
    }
    .reveal.is-visible {
      opacity: 1;
      transform: translateY(0);
    }
    @media (prefers-reduced-motion: reduce) {
      .reveal { opacity: 1; transform: none; transition: none; }
    }
  </style>
</head>
<body>
  <a class="skip" href="#main">Skip to content</a>

  <header class="site">
    <div class="wrap">
      <div class="brand">
        <a class="brand-name" href="/">scrappy<em>dolls</em></a>
        <a class="brand-attribution" href="https://www.facebook.com/kandakayartist/" target="_blank" rel="noopener" aria-label="From Art Safari Studio, handmade by Kanda Kay — visit on Facebook">
          <span class="brand-by">from Art Safari Studio</span>
          <span class="brand-artist">Handmade by Kanda Kay</span>
        </a>
      </div>
      <nav class="primary" aria-label="Primary">
        <a href="/shop/" class="priority">Shop</a>
        <a href="#about">About</a>
        <a href="#process">Process</a>
        <a href="#gallery">Gallery</a>
        <a href="#faq">FAQ</a>
        <a class="btn-mini" href="https://www.facebook.com/kandakayartist/" rel="noopener">Follow</a>
      </nav>
    </div>
  </header>

  <main id="main">

    <!-- HERO -->
    <section class="hero">
      <div class="wrap">
        <div class="hero-grid">
          <div>
            <p class="eyebrow">Handmade by Kanda Kay</p>
            <h1 class="h-display">Cloth dolls,<br>stitched <em style="color: var(--rose); font-style: italic; font-weight: 400;">one&nbsp;at&nbsp;a&nbsp;time</em>.</h1>
            <p class="lede" style="margin-top: 1.75rem;">Scrappy Dolls is a small studio of one-of-a-kind cloth dolls and custom memory dolls — each hand-cut and hand-sewn from quilting cottons, vintage prints, and beloved fabric remnants too lovely to throw away.</p>
            <div class="cta-row">
              <a class="btn btn-primary" href="https://www.facebook.com/kandakayartist/" rel="noopener">
                Follow on Facebook <span class="arrow" aria-hidden="true">→</span>
              </a>
              <a class="btn btn-ghost" href="#gallery">See the dolls</a>
            </div>
            <div class="hero-meta">
              <div>
                <span class="k">100%</span>
                <span class="v">Handmade</span>
              </div>
              <div>
                <span class="k">1 of 1</span>
                <span class="v">Every doll</span>
              </div>
              <div>
                <span class="k">∞</span>
                <span class="v">Stitches of love</span>
              </div>
            </div>
          </div>
          <div class="hero-image-stack reveal">
            <div class="frame">
              <img src="images/doll-rainbow-hair.jpg" alt="Handmade Scrappy Doll by Kanda Kay with multicolored yarn hair, embroidered features, and a vibrant patchwork dress with lace trim" width="800" height="1000" fetchpriority="high">
            </div>
            <div class="badge">No two <em>alike</em></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ABOUT -->
    <section id="about">
      <div class="wrap">
        <div class="about-grid">
          <div class="about-portrait reveal" role="img" aria-label="A handmade Scrappy Doll by Kanda Kay — brown curly hair, a poppy headband, and a green floral dress"></div>
          <div class="reveal">
            <p class="eyebrow">The Studio</p>
            <h2 class="h-display">Made by hand.<br>Made <em style="color: var(--rose); font-style: italic; font-weight: 400;">to keep</em>.</h2>
            <p class="pull-quote">Every Scrappy Doll begins as a pile of fabric — quilt offcuts, an old pillowcase, the last good piece of a favorite shirt.</p>
            <p style="margin-top: 1.5rem; color: var(--ink-soft);">Kanda Kay cuts, pieces, and stitches each doll by hand, finishing with embroidered features and a name only that doll will ever wear. The result is a small, sturdy companion — warm-feeling, washable, and unmistakably one of a kind.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- PROCESS -->
    <section id="process" class="alt">
      <div class="wrap">
        <div class="reveal" style="max-width: 36rem;">
          <p class="eyebrow">The Process</p>
          <h2 class="h-display">From scraps<br>to <em style="color: var(--rose); font-style: italic; font-weight: 400;">heirloom</em>.</h2>
        </div>
        <div class="process">
          <article class="step reveal">
            <h3>Gather</h3>
            <p>Vintage prints, quilt remnants, and meaningful scraps — every doll begins with fabric that already has a story.</p>
          </article>
          <article class="step reveal">
            <h3>Cut &amp; piece</h3>
            <p>Pattern pieces are hand-cut, then arranged and pieced into a unique combination of color, weight, and texture.</p>
          </article>
          <article class="step reveal">
            <h3>Stitch</h3>
            <p>Each seam is sewn by hand for strength and character. Faces are embroidered with thread; nothing is glued or printed.</p>
          </article>
          <article class="step reveal">
            <h3>Finish</h3>
            <p>Hair, jewelry, dresses, and details are added one at a time until a doll has clearly arrived as itself.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- BIO -->
    <section id="artist">
      <div class="wrap">
        <div class="bio-grid">
          <div class="bio-portrait reveal">
            <img src="images/kanda-kay.png" alt="Kanda Kay — artist and maker behind Scrappy Dolls" loading="lazy" width="1648" height="1366">
          </div>
          <div class="reveal">
            <p class="eyebrow">Meet Kanda</p>
            <h2 class="h-display">A lifetime of <em style="color: var(--rose); font-style: italic; font-weight: 400;">making</em>.</h2>
            <p class="lede" style="margin-top: 1.5rem;">Kanda grew up in a family of painters, photographers, musicians, and seamstresses — making was simply the language spoken at home.</p>
            <p style="margin-top: 1.25rem; color: var(--ink-soft);">She studied art education at the University of Kansas and, after graduating, opened her own weaving shop. While homeschooling her three children, she kept creative work at the center of family life — and watched that next generation grow into artists, musicians, graphic designers, and web developers in their own right.</p>
            <p style="margin-top: 1rem; color: var(--ink-soft);">In retirement, she founded Art Safari Studio and has never stopped making. Her work there has gravitated toward combining everyday materials — quilt offcuts, vintage prints, the last good piece of a beloved shirt — into one-of-a-kind pieces. Scrappy Dolls is where that lifelong practice has landed.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- MARQUEE -->
    <?php if ($carouselDolls): ?>
    <section class="marquee" aria-label="Recent dolls scrolling">
      <div class="marquee-track">
        <?php foreach ($carouselDolls as $d): if (empty($d['thumb'])) continue; ?>
          <a class="marquee-item" href="/shop/product.php?slug=<?= h(urlencode($d['slug'])) ?>" title="<?= h($d['title']) ?><?= $d['status']==='sold' ? ' — sold' : ' · ' . fmt_price((int)$d['price_cents']) ?>">
            <img src="<?= h(asset_url($d['thumb'])) ?>" alt="<?= h($d['title']) ?>" loading="lazy">
            <?php if ($d['status'] === 'sold'): ?><span class="marquee-sold">Sold</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <!-- Duplicates for seamless loop -->
        <?php foreach ($carouselDolls as $d): if (empty($d['thumb'])) continue; ?>
          <a class="marquee-item" href="/shop/product.php?slug=<?= h(urlencode($d['slug'])) ?>" aria-hidden="true" tabindex="-1">
            <img src="<?= h(asset_url($d['thumb'])) ?>" alt="" loading="lazy">
            <?php if ($d['status'] === 'sold'): ?><span class="marquee-sold">Sold</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ROSTER (live store dolls) -->
    <section id="gallery">
      <div class="wrap">
        <div class="gallery-head reveal">
          <div>
            <p class="eyebrow">Available Now</p>
            <h2 class="h-display">A roster of <em style="color: var(--rose); font-style: italic; font-weight: 400;">characters</em>.</h2>
          </div>
          <p>A live look at the studio. Click any doll to take her home.</p>
        </div>

        <?php if ($rosterDolls): ?>
          <div class="roster">
            <?php foreach ($rosterDolls as $d): ?>
              <a class="roster-card" href="/shop/product.php?slug=<?= h(urlencode($d['slug'])) ?>">
                <?php if ($d['thumb']): ?>
                  <img src="<?= h(asset_url($d['thumb'])) ?>" alt="<?= h($d['title']) ?>" loading="lazy">
                <?php else: ?>
                  <div class="roster-placeholder"></div>
                <?php endif; ?>
                <div class="roster-meta">
                  <span class="roster-title"><?= h($d['title']) ?></span>
                  <?php if ($d['status'] === 'sold'): ?>
                    <span class="roster-tag sold">Sold</span>
                  <?php else: ?>
                    <span class="roster-price"><?= fmt_price((int)$d['price_cents']) ?></span>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
          <div style="text-align:center;margin-top:2.5rem">
            <a class="btn btn-primary" href="/shop/">
              Browse the full shop <span class="arrow" aria-hidden="true">→</span>
            </a>
          </div>
        <?php else: ?>
          <div class="empty-shop" style="background:var(--paper-2);border:1px dashed var(--rule);border-radius:var(--radius);padding:4rem 2rem;text-align:center;color:var(--ink-soft)">
            <h3 style="font-family:var(--font-display);font-size:1.5rem;margin:0 0 .5rem;color:var(--ink)">Fresh dolls are on the table</h3>
            <p>New work appears first on Facebook — <a href="https://www.facebook.com/kandakayartist/" rel="noopener">follow along</a> to see them as they're finished.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="testimonials alt">
      <div class="wrap">
        <div class="testimonials-head reveal">
          <p class="eyebrow">Kind Words</p>
          <h2 class="h-display">From <em style="color: var(--rose); font-style: italic; font-weight: 400;">collectors</em>.</h2>
        </div>
        <div class="testimonials-grid">
          <figure class="testimonial-card reveal">
            <blockquote>Kanda is an AMAZING artist! She is friendly, professional, very reasonable in pricing, and responsive. We are SO HAPPY with the final product — she captured our furr-babes so perfectly in her whimsical, fun way!</blockquote>
            <figcaption>
              <cite>Carrie S.</cite>
              <span class="meta">Commissioned pet portraits</span>
            </figcaption>
          </figure>
          <figure class="testimonial-card reveal">
            <blockquote>I know and recommend this artist — she is amazing and talented. One of a kind.</blockquote>
            <figcaption>
              <cite>Albert H.</cite>
              <span class="meta">Collector</span>
            </figcaption>
          </figure>
          <figure class="testimonial-card reveal">
            <blockquote>This artist is magic! I have quite a few pieces, plus one that was specifically commissioned.</blockquote>
            <figcaption>
              <cite>Terise B.</cite>
              <span class="meta">Collector &amp; commission client</span>
            </figcaption>
          </figure>
        </div>
      </div>
    </section>

    <!-- WHAT ARE SCRAPPY DOLLS -->
    <section id="what-are-scrappy-dolls">
      <div class="wrap-narrow">
        <div class="reveal">
          <p class="eyebrow">The Tradition</p>
          <h2 class="h-display">What are <em style="color: var(--rose); font-style: italic; font-weight: 400;">scrappy dolls</em>?</h2>
        </div>
        <div class="reveal" style="margin-top: 2rem; color: var(--ink-soft); line-height: 1.75;">
          <p>Scrappy dolls are handmade cloth dolls stitched from leftover fabric — quilting cottons, vintage prints, worn-out clothing, and remnants too small for anything else but too beautiful to discard. The tradition runs centuries deep. Rag dolls are among the oldest children's toys in existence, with examples found in Roman-era graves dating to the first century AD. In early America, mothers and grandmothers fashioned dolls from household scraps — old dresses, flour sacks, handkerchiefs — because manufactured toys were either unavailable or unaffordable. Appalachian folk dolls, prairie dolls, and Amish faceless dolls all grew from this same impulse: take what you have and make something worth keeping.</p>
          <p>What sets scrappy dolls apart from mass-produced toys is the material itself. Every scrap carries a history — a quilt that wore through, a child's outgrown shirt, the last cut from a bolt of fabric a grandmother picked out. The doll becomes a vessel for those stories. No two scrappy dolls look alike because no two fabric piles are the same. The wonky proportions, mismatched prints, and hand-stitched imperfections are not flaws. They are the entire point.</p>
          <p>Today, scrappy dolls have become a movement. Online communities — Facebook groups, Pinterest boards, and craft forums — connect thousands of makers who share techniques, trade fabric, and celebrate the deliberate imperfection that makes each doll one of a kind. The craft appeals to experienced quilters looking to use their scrap stash, to parents seeking meaningful handmade gifts, and to collectors drawn to folk art with genuine provenance and personality.</p>
        </div>
      </div>
    </section>

    <!-- TECHNIQUES & APPEAL -->
    <section class="alt">
      <div class="wrap-narrow">
        <div class="reveal">
          <p class="eyebrow">The Craft</p>
          <h2 class="h-display">Why scrappy dolls <em style="color: var(--rose); font-style: italic; font-weight: 400;">endure</em>.</h2>
        </div>
        <div class="reveal" style="margin-top: 2rem; color: var(--ink-soft); line-height: 1.75;">
          <h3>Materials that matter</h3>
          <p>The best scrappy dolls start with quilter's cotton — sturdy enough to handle stuffing, soft enough to hold and love. Vintage prints, ditsy florals, feedsack reproductions, bold stripes, and novelty fabrics all find their way into the mix. The fabric choices give each doll its character before a single stitch is sewn. Some makers sort scraps by color; others grab from the pile and let the combinations surprise them. Both approaches work because the aesthetic lives in the collision of patterns, not in any single fabric.</p>
          <h3>Handwork over machine work</h3>
          <p>Many scrappy doll makers — including Kanda Kay — work entirely by hand. Hand-cutting means every piece follows the curve of the available fabric rather than forcing a rigid pattern. Hand-stitching produces seams with character and strength that hold up to years of handling. Faces are embroidered with thread rather than printed or stamped, giving each doll an expression that could only belong to that one creation. Nothing is glued. Nothing is mass-produced. The process is slow by design.</p>
          <h3>Sustainability built in</h3>
          <p>Scrappy dolls are zero-waste craft at its most natural. Quilt offcuts, retired clothing, bolt ends, and fabric remnants all become raw material. Between 1900 and 1920, over five million rag dolls were produced annually in the United States, fueled in part by textile waste from cotton mills. A century later, the same logic holds: fabric that would otherwise sit in a drawer or end up in a landfill becomes a keepsake instead. Every scrappy doll is a small act of reclamation.</p>
          <h3>The appeal of imperfection</h3>
          <p>In a market saturated with identical plastic toys, scrappy dolls offer something manufacturers cannot replicate: genuine singularity. The slightly uneven ears, the seam that follows the fabric's grain instead of a laser-cut line, the mismatched buttons — these details are what collectors and gift-givers seek out. A scrappy doll is proof that a human hand made it, and that the person who made it cared more about soul than symmetry. That is why the scrappy doll community continues to grow, and why the best examples are kept for generations.</p>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq">
      <div class="wrap-narrow">
        <div class="reveal" style="margin-bottom: 2.5rem;">
          <p class="eyebrow">Frequently Asked</p>
          <h2 class="h-display">Good <em style="color: var(--rose); font-style: italic; font-weight: 400;">questions</em>.</h2>
        </div>
        <!-- TODO: Confirm answers with Kanda before publishing — these are reasonable defaults, not commitments. -->
        <details class="faq">
          <summary>Are dolls available to purchase?</summary>
          <p>New dolls are announced on the <a href="https://www.facebook.com/kandakayartist/" rel="noopener">Scrappy Dolls Facebook page</a> as they're finished. Send a message there to ask about what's currently available.</p>
        </details>
        <details class="faq">
          <summary>Can a doll be made from my own fabric?</summary>
          <p>Memory dolls — made from a child's outgrown clothing, a wedding dress, a beloved quilt — are part of what scrappy dolls are best at. Reach out via Facebook to talk through your fabric and what you'd like.</p>
        </details>
        <details class="faq">
          <summary>Are Scrappy Dolls safe for children?</summary>
          <p>Dolls are sewn with sturdy seams and embroidered features (no buttons or small attached parts), and stuffed with fiberfill. They make wonderful keepsakes and play companions for older babies and children.</p>
        </details>
        <details class="faq">
          <summary>How do I care for a Scrappy Doll?</summary>
          <p>Spot clean with a damp cloth and mild soap. For a deeper clean, hand-wash gently in cool water and reshape while damp; lay flat to dry.</p>
        </details>
        <details class="faq">
          <summary>How long does it take to make a doll?</summary>
          <p>It depends on the fabric, the character, and the level of detail. A doll can take anywhere from an afternoon to several days — and each one tells you when it's done.</p>
        </details>
      </div>
    </section>

    <!-- FOLLOW -->
    <section id="follow">
      <div class="wrap">
        <div class="follow-card reveal">
          <div>
            <p class="eyebrow" style="color: var(--rose-light);">Stay close</p>
            <h2 class="h-display">See new dolls<br>as they're <em style="color: var(--rose-light); font-style: italic; font-weight: 400;">finished</em>.</h2>
            <p style="margin-top: 1rem;">Follow Scrappy Dolls on Facebook for new work, sneak peeks of what's on the table, and the stories behind the fabric.</p>
          </div>
          <a class="btn btn-primary" href="https://www.facebook.com/kandakayartist/" rel="noopener">
            Follow on Facebook <span class="arrow" aria-hidden="true">→</span>
          </a>
        </div>
      </div>
    </section>
  </main>

  <footer class="site">
    <div class="wrap">
      <div class="row">
        <div>
          <p class="sig">Scrappy Dolls</p>
          <p style="margin: 0;"><a href="https://www.facebook.com/kandakayartist/" rel="noopener">from Art Safari Studio · Handmade by Kanda Kay</a></p>
        </div>
        <div style="text-align: right;">
          <p style="margin: 0 0 0.5rem;"><a href="https://www.facebook.com/kandakayartist/" rel="noopener">Facebook</a></p>
          <p class="legal">&copy; <span id="y"></span> Scrappy Dolls · San Antonio, Texas.</p>
        </div>
      </div>
    </div>
  </footer>

  <!-- Lightbox -->
  <dialog id="lightbox" class="lightbox" aria-label="Image viewer">
    <img id="lightbox-img" src="" alt="">
    <button class="lightbox-btn lightbox-close" type="button" aria-label="Close">&times;</button>
    <button class="lightbox-btn lightbox-prev" type="button" aria-label="Previous image">&lsaquo;</button>
    <button class="lightbox-btn lightbox-next" type="button" aria-label="Next image">&rsaquo;</button>
  </dialog>

  <script>
    document.getElementById('y').textContent = new Date().getFullYear();

    // Reveal-on-scroll
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add('is-visible');
            io.unobserve(e.target);
          }
        });
      }, { rootMargin: '0px 0px -10% 0px', threshold: 0.05 });
      document.querySelectorAll('.reveal').forEach((el) => io.observe(el));
    } else {
      document.querySelectorAll('.reveal').forEach((el) => el.classList.add('is-visible'));
    }

    // Lightbox
    (function () {
      const lightbox = document.getElementById('lightbox');
      if (!lightbox) return;
      const lightboxImg = document.getElementById('lightbox-img');
      const figures = Array.from(document.querySelectorAll('.gallery figure'));
      const items = figures.map((f) => f.querySelector('img')).filter(Boolean);
      if (!items.length) return;
      let current = 0;

      const show = (i) => {
        if (i < 0) i = items.length - 1;
        if (i >= items.length) i = 0;
        current = i;
        lightboxImg.src = items[i].src;
        lightboxImg.alt = items[i].alt || '';
      };

      const open = (i) => {
        show(i);
        if (typeof lightbox.showModal === 'function') lightbox.showModal();
        else lightbox.setAttribute('open', '');
      };

      figures.forEach((figure, i) => {
        figure.setAttribute('role', 'button');
        figure.setAttribute('tabindex', '0');
        const altText = figure.querySelector('img')?.alt || 'doll image';
        figure.setAttribute('aria-label', 'View larger: ' + altText);
        figure.addEventListener('click', () => open(i));
        figure.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            open(i);
          }
        });
      });

      lightbox.querySelector('.lightbox-close').addEventListener('click', () => lightbox.close());
      lightbox.querySelector('.lightbox-prev').addEventListener('click', (e) => {
        e.stopPropagation();
        show(current - 1);
      });
      lightbox.querySelector('.lightbox-next').addEventListener('click', (e) => {
        e.stopPropagation();
        show(current + 1);
      });

      lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) lightbox.close();
      });

      document.addEventListener('keydown', (e) => {
        if (!lightbox.open) return;
        if (e.key === 'ArrowLeft') show(current - 1);
        else if (e.key === 'ArrowRight') show(current + 1);
      });
    })();
  </script>

  <!-- FAQ structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
      {
        "@type": "Question",
        "name": "Are dolls available to purchase?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "New dolls are announced on the Scrappy Dolls Facebook page as they're finished. Send a message there to ask about what's currently available."
        }
      },
      {
        "@type": "Question",
        "name": "Can a doll be made from my own fabric?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Memory dolls — made from a child's outgrown clothing, a wedding dress, a beloved quilt — are part of what scrappy dolls are best at. Reach out via Facebook to talk through your fabric and what you'd like."
        }
      },
      {
        "@type": "Question",
        "name": "Are Scrappy Dolls safe for children?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Dolls are sewn with sturdy seams and embroidered features (no buttons or small attached parts), and stuffed with fiberfill. They make wonderful keepsakes and play companions for older babies and children."
        }
      },
      {
        "@type": "Question",
        "name": "How do I care for a Scrappy Doll?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Spot clean with a damp cloth and mild soap. For a deeper clean, hand-wash gently in cool water and reshape while damp; lay flat to dry."
        }
      },
      {
        "@type": "Question",
        "name": "How long does it take to make a doll?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "It depends on the fabric, the character, and the level of detail. A doll can take anywhere from an afternoon to several days — and each one tells you when it's done."
        }
      }
    ]
  }
  </script>

  <!-- Customer reviews structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Review",
        "itemReviewed": { "@id": "https://scrappydolls.com/#business" },
        "author": { "@type": "Person", "name": "Carrie S." },
        "datePublished": "2017-10-25",
        "reviewBody": "Kanda is an AMAZING artist! She is friendly, professional, very reasonable in pricing, and responsive. We are SO HAPPY with the final product — she captured our furr-babes so perfectly in her whimsical, fun way!"
      },
      {
        "@type": "Review",
        "itemReviewed": { "@id": "https://scrappydolls.com/#business" },
        "author": { "@type": "Person", "name": "Albert H." },
        "datePublished": "2019-09-12",
        "reviewBody": "I know and recommend this artist — she is amazing and talented. One of a kind."
      },
      {
        "@type": "Review",
        "itemReviewed": { "@id": "https://scrappydolls.com/#business" },
        "author": { "@type": "Person", "name": "Terise B." },
        "datePublished": "2022-10-30",
        "reviewBody": "This artist is magic! I have quite a few pieces, plus one that was specifically commissioned."
      }
    ]
  }
  </script>
</body>
</html>
