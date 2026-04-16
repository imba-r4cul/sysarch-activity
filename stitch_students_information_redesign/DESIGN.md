# Design System Document: The Academic Ledger

## 1. Overview & Creative North Star
The objective of this design system is to transform a functional administrative tool into a high-end editorial experience. We are moving away from the "legacy portal" aesthetic—characterized by cramped tables and harsh borders—toward a philosophy we call **"The Academic Ledger."**

The Academic Ledger treats data with the reverence of a premium publication. It utilizes generous white space, intentional asymmetry in layout, and sophisticated tonal layering to create an environment that feels authoritative yet effortless. By leveraging a high-contrast typography scale and "borderless" containment, we ensure the College Admin portal feels like a modern, digital-first institution rather than a database entry form.

## 2. Colors
This system breathes new life into the traditional collegiate palette of deep blue and crimson red through a sophisticated Material Design 3 implementation.

*   **Primary (`#004085`)**: Used for the most critical actions and navigational anchors. It represents institutional trust.
*   **Secondary (`#b6171e`)**: Reserved for corrective actions (Reset, Delete) and high-priority status indicators. It is used sparingly to maintain its visual impact.
*   **Tertiary (`#722b00`)**: An earthy accent used for subtle highlighting or administrative metadata.

### The "No-Line" Rule
To achieve a contemporary editorial feel, **1px solid borders are strictly prohibited for sectioning.** Boundaries must be defined through background color shifts. For example, a data table header should sit on `surface-container-low` against a `surface` background. Information is grouped by proximity and tonal contrast, not by "boxing it in."

### Surface Hierarchy & Nesting
Treat the UI as a series of stacked, physical layers of fine paper.
*   **Level 0 (Base):** `surface` (`#f8f9fa`)
*   **Level 1 (Sections):** `surface-container-low` (`#f3f4f5`)
*   **Level 2 (Cards/Interactives):** `surface-container-lowest` (`#ffffff`)

### The Glass & Gradient Rule
Main CTAs and hero headers should utilize subtle linear gradients (e.g., `primary` to `primary_container`) to provide "soul." For floating elements like dropdowns or overlays, use **Glassmorphism**: apply `surface_container_lowest` at 80% opacity with a `16px` backdrop-blur.

## 3. Typography
The system employs a dual-font strategy to balance character with utility.

*   **The Display & Headline (Manrope):** A modern, geometric sans-serif that provides a clean, contemporary voice. Use `display-md` for page titles like "Students Information" to establish a clear visual anchor.
*   **The Utility (Inter):** A highly legible typeface used for the "Ledger" (the data table) and body copy. Inter’s tall x-height ensures clarity even in dense data environments.
*   **Intentional Scale:** Create a dramatic hierarchy by pairing a `headline-lg` title with `label-md` metadata. The contrast between large, bold headers and small, all-caps labels is a hallmark of high-end editorial design.

## 4. Elevation & Depth
Depth is conveyed through **Tonal Layering** rather than traditional drop shadows.

*   **The Layering Principle:** To "lift" a component, place a `surface-container-lowest` card on top of a `surface-container` background. This creates a soft, natural separation.
*   **Ambient Shadows:** Where a "floating" effect is necessary (e.g., a modal), use a shadow with a `24px` blur at 6% opacity, tinted with the `on-surface` color.
*   **The Ghost Border Fallback:** If a divider is required for extreme accessibility needs, use a "Ghost Border": the `outline_variant` token at **15% opacity**. Never use a 100% opaque border.

## 5. Components

### Data Tables (The Ledger)
*   **Header:** Use `surface-container-low` with `label-md` typography in `on-surface-variant`.
*   **Rows:** Strictly no horizontal or vertical lines. Use `surface` for the background and `surface-container-lowest` for the row on hover to indicate interactivity.
*   **Spacing:** Use `spacing-4` (1.4rem) for vertical cell padding to create a "spacious" layout that reduces cognitive load.

### Buttons
*   **Primary:** `primary` background with `on-primary` text. Use `rounded-md` (0.375rem). Use a subtle gradient to `primary-container` for a premium finish.
*   **Secondary:** `secondary` background. Use only for destructive or reset actions to maintain the color's weight.
*   **Tertiary:** Transparent background with `primary` text. No border; use for low-priority navigation.

### Search & Inputs
*   **Styling:** Inputs should use `surface-container-highest` with a `2px` bottom-only focus indicator in `primary`. Forgo the four-sided box for a more "open" editorial feel.
*   **Helper Text:** Use `label-sm` in `on-surface-variant`.

### Chips & Badges
*   **Context:** Use for Year Levels or Courses.
*   **Style:** Use `primary-fixed` with `on-primary-fixed` text. Roundedness should be `full` to contrast against the more rectangular table structure.

## 6. Do's and Don'ts

### Do
*   **DO** use `spacing-10` and `spacing-12` for page margins to create a high-end, spacious feel.
*   **DO** align text to the left for data tables, but allow for right-aligned numerical data to maintain the "Ledger" precision.
*   **DO** use `manrope` for all numbers in headers to capitalize on its geometric beauty.

### Don't
*   **DON'T** use 1px solid lines to separate table rows. Use white space (`spacing-3`).
*   **DON'T** use standard "Web Blue" or "Web Red." Stick strictly to the provided tonal variants like `primary_fixed` and `secondary_container`.
*   **DON'T** crowd the navigation. Maintain the existing structure but use `title-sm` with increased letter spacing to modernize the links.

## 7. Layout Exceptions

*   **No Navbar & Footer:** To maintain the "pure editorial" focus and maximize vertical data real estate, this project does not include a global navigation bar or a footer on the canvas. Navigation should be handled via contextual in-page triggers or side-panels if necessary.