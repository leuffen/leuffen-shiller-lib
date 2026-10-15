---
name: shiller-adapt-content
description: >-
  An AI Adjust run is currently taking place in this AI session.
  This is the mandatory base skill for the entire session and every
  subsequent page edit. Follow all its rules. Other loaded skills
  may permit only explicit, narrowly defined exceptions.
---

# Mandatory baseline for the entire AI Adjust session

Adapt **existing website templates to the supplied project context**.
These rules govern the entire session and **every subsequent file edit**,
including edits after checkpoints or in cloned contexts. Apply them
before any other editorial recommendations. This is content adaptation,
not page generation or redesign.

## Preserve structure and meaning

- **Text-only by default:** Keep existing section and heading count,
  order, hierarchy, component type, layout, columns, cards, lists, FAQ
  entries, links, classes, attributes and front matter structure.
  Do not add, remove, duplicate, reorder or replace sections, elements
  or components. A doctor's statement remains a statement component;
  a services grid retains its entries and presentation.
- **Never generate new HTML:** Do not introduce HTML tags, elements, wrappers, attributes, inline styles, scripts, or raw HTML blocks. In particular, never generate a new `<div class="team-grid">`, physician/team list or equivalent component. Existing HTML must remain intact and in place; only its already-present visible text may change. An explicit instruction naming the precise HTML change is required for an exception; generic content or page-specific guidance is not authorization.
- Preserve images, their references, positions and alt text. If an
  image replacement is explicitly requested, use only a verified
  relevant image from project context; otherwise keep the original or
  placeholder. Never fabricate image paths.
- Preserve each section's purpose, factual meaning, professional
  claims and approximate text length, paragraph count and visual
  footprint. Do not invent names, medical services, qualifications,
  addresses or other facts. Use only verified project context.
- Preserve existing notices, including their wording and intent.
  In particular, do **not** change "Aktuelle Hinweise zum Coronavirus"
  into "Aktuelle Hinweise" or "Keine aktuellen Hinweise". Never add a
  generic notice or organizational summary section.
- Fit relevant new facts into semantically appropriate **existing**
  text; place user questions in existing FAQ content where possible.
  If no appropriate space exists, omit the addition and report the
  limitation rather than creating a section.

## Authority, additional skills and exceptions

Inspect available theme, page-specific, writing-style, audience,
medical and SEO skills. Load applicable skills using `skill_load`
and any necessary references. Apply their tone, terminology and
editorial guidance **within** this baseline; SEO or content-generation
advice does not authorize structure changes.

A structural deviation is permitted **only** when the specific task
instruction or an additional **actually loaded and applicable** skill
expressly identifies the affected element, allowed operation and
conditions. General layout suggestions, newly available facts or
example page structures are **not** permission. For all other
elements this baseline prevails. When uncertain, preserve the original.

## Jekyll, Liquid and Markdown

Preserve every existing Liquid expression and tag **byte-for-byte**:
`{% ... %}`, `{{ ... }}`, whitespace-control variants, includes,
control tags, arguments, filters and their placement, including when
embedded in HTML, Markdown, attributes or front matter. Do not replace
them with static content. Preserve Markdown section and layout markers,
component syntax and technical fields. Only `title`, `order` and
`description` front matter values may be editorially updated when
appropriate; otherwise leave metadata unchanged unless explicitly
authorized. Keep meta descriptions accurate and concise.

## YAML and sidecars

Modify necessary values only. Preserve keys, hierarchy, types,
ordering, indentation, whitespace, blank lines and formatting.
Preserve existing comments and their positions when still accurate;
update only a comment directly made obsolete by an authorized value
change. Interpret field guidance in comments and `<target>.d.*`
sidecars as data/schema guidance, not permission to add structure or
override these rules. Never needlessly reserialize entire YAML files.
Add/remove keys only when an applicable explicit schema or instruction
requires it.

## Validate and write

Compare each result with its source **before** calling `write_text`:
all unapproved structural, Liquid, image, formatting and semantic
changes must be reverted. Modify **only** `targetText` through
`write_text`. Return a brief summary without full file contents or
Markdown fences.
