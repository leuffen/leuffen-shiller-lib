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
- Preserve the number, positions, element types and Markdown/HTML
  syntax of all existing images by default: do not remove image
  elements or add images at new positions unless an applicable,
  explicitly loaded subsequent skill permits this operation. For each existing image,
  compare its purpose with the supplied project context and update its
  image URL/reference to a verified, context-appropriate image when
  available. Do not keep an unrelated template image merely to preserve
  its old URL. Keep the current reference only when no suitable verified
  replacement is available; report unresolved image positions instead
  of inventing paths. For portraits and identifiable teams, require an
  explicit context-to-person/team mapping; never imply an unrelated
  stock photo depicts an actual staff member. Keep alt text unless it
  becomes inaccurate after an authorized image change; then adjust
  only the descriptive alt-text value, retaining image syntax.
- Preserve each section's purpose, factual meaning, professional
  claims and approximate text length, paragraph count and visual
  footprint. Do not invent names, medical services, qualifications,
  addresses or other facts. Use only verified project context.
- **Preserve good existing wording:** Keep existing copy unchanged whenever
  it is compatible with verified project facts. Missing, unknown, unconfirmed
  or unspecified project information alone does not authorize rewriting valid,
  project-neutral wording.
- **Never preserve foreign or demo facts:** Wording protection does not apply
  to recognizable template, example or previous-customer data, or to claims
  that conflict with verified project facts. Check names, locations, medical
  specialties, services, vacancies, job duties, software, benefits and working
  conditions explicitly. Replace every incompatible value; never leave it in
  place merely because a verified replacement detail is unavailable.
- **Adapt foreign demo copy into plausible project copy:** When a template
  block contains project-specific demo content but the corresponding project
  facts are missing, unknown or unconfirmed, keep the existing structure and
  rewrite the demo text into concise, natural, publication-ready copy that fits
  the verified project type, location, specialty and page purpose. Generalize
  unsupported specifics instead of preserving them or substituting uncertainty
  notices. Do not emit placeholders, editorial notes, statements such as "not
  confirmed", invented vacancies, benefits, procedures or other factual claims.
  The result must read like intentional website copy while remaining true under
  the known context. Missing facts are not a file-edit failure and must not
  abort or skip the adaptation.
- **Use the smallest justified text diff:** Replace wording only where a
  specific verified project fact requires the change, and alter only the
  affected words or sentences. Do not rewrite surrounding valid copy merely
  for stylistic consistency, variation, SEO or a more cautious tone. Every
  textual change must be traceable to a concrete project fact or explicit
  instruction.
- **Do not weaken calls to action because information is absent:** In
  particular, do not turn established button labels into generic phrases such
  as "Questions about ..." or "Information about ..." merely because a
  workflow, availability or service is undocumented. Preserve the existing
  label when it remains project-neutral. If it contains a foreign, demo or
  contradicted claim, replace it with concise, plausible, project-appropriate
  wording that makes no unsupported factual claim instead of retaining the
  claim, inserting a placeholder or aborting the edit.
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

For image changes, explicitly check and follow additional applicable
image-specific rules in subsequently loaded skills and references.
Such rules may authorize replacing image URLs, adding new image
positions or removing existing images, but only when they clearly
specify the affected image or section, allowed operation and conditions.
Without such explicit permission, preserve the existing image positions
and count; adapting a URL in place does not itself authorize adding
or removing an image.

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
