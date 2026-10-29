---
name: "Respawn Night Feed"
description: "Dark, dense gaming news portal: charcoal ground, one signal-orange accent, wide Unbounded brand type over a tight Onest reading face."
colors:
  bg: "#0F1114"
  surface: "#171A1F"
  surface-2: "#1F232A"
  line: "#2A2F37"
  ink: "#ECEEF1"
  muted: "#9AA2AE"
  accent: "#FF5B2E"
  accent-ink: "#0F1114"
  good: "#4FD18B"
  bad: "#FF6B6B"
typography:
  heading:
    fontFamily: "Onest, sans-serif"
    fontWeight: "750"
  body:
    fontFamily: "Onest, sans-serif"
    fontWeight: "400"
  display:
    fontFamily: "Unbounded, sans-serif"
    fontWeight: "700"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "40px"
  xxl: "64px"
rounded:
  sm: "6px"
  md: "10px"
  pill: "999px"
components:
  buttons: "Solid accent with accent-ink text, 6px radius; secondary is a 1px line outline. Pill radius only for tags and vote counters."
  cards: "Story cards are flat: image on top, title below, no shadow. Separation by gap and a 1px line, not elevation."
  score: "Review score is a square accent block with the number in Unbounded."
dials:
  variance: 0.4
  density: 0.7
  motion: 0.15
---

## Overview

Respawn is a free public gaming portal: news, reviews, guides and a forum, with articles written by the community. The reader comes to scan a lot of headlines fast and dive into a few. The design is a dark, dense news feed with one loud signal colour, closer to a newsroom at night than to a neon arcade.

## Colors

Charcoal ground #0F1114 with slightly cool, blue-biased neutrals. Signal orange #FF5B2E is the single accent: links on hover, primary buttons, the active nav item, review scores, the like state. Green and red appear only inside review pros and cons.

## Typography

Unbounded (wide, geometric) is the brand voice: logo, section titles, review score. It never sets long news headlines. Onest carries headlines (weight 700 to 800, line-height 1.1 to 1.2) and body (17px, line-height 1.65, measure about 68ch). Uppercase labels get 0.06em tracking.

## Layout

One shared 1280px container. Home is a lead story plus a dense side list, then a feed with a sticky sidebar (popular, forum, categories). Cards collapse to one column under 720px.

## Elevation & Depth

No drop shadows. Depth comes from surface steps (bg, surface, surface-2) and 1px lines.

## Shapes

6px radius on cards, images and buttons; pills only for tags and counters.

## Components

Header: logo, one-line category nav, search, write and login actions. Story card, list row, review box with score, pros, cons and game facts, like button with count, author card with rating.

## Do's and Don'ts

### Do's
- Use the accent for one primary action per view.
- Keep headlines in Onest and the brand voice in Unbounded.
- Show real counts (likes, comments, views) only when they exist.
- Use an on-palette gradient placeholder when a post has no image.

### Don'ts
- Never add neon glows, purple gradients or cursor tilt effects.
- Never set a long news headline in Unbounded.
- Avoid cards with shadows; separate with lines and gaps.
- Don't use em-dashes in interface copy.
