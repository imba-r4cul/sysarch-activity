# Notes — dashboard.php: Protected styles

Overview

- This file lists CSS classes and color values referenced by [public/dashboard.php](public/dashboard.php#L1) that should not be changed without coordination.

Primary files referenced

- [public/css/style.css](public/css/style.css#L1)
- [public/css/dashboard.css](public/css/dashboard.css#L1)

Protected colors (do not change)

- Primary blues (brand)
  - `#0b4b8f` — used in `.panel-header`, `.btn-primary` gradients, `.section-title-icon`, `.profile-actions`, and `#closeSuccessModal`.
  - `#18539a` — used in the `.navbar` background and login button gradients.
  - `#1a6fd4` — complementary blue used in button gradients.
  - `#2471c9` — gradient accent used in login buttons/modals.

- Neutral / background / borders
  - `#efefef` — `.dashboard-body` background (dashboard.css).
  - `#eff1f3` — global `body` background (style.css).
  - `#fff` — card/panel backgrounds.
  - `#c9c9c9` — panel border.
  - `#ededed` — `.student-item` top border.
  - `#8f8c8c` — avatar border.
  - `#f9f9f9` — avatar background.
  - `#e0e0e0` — form input border.
  - `#fcfcfc` — form input background.
  - `#a39cf4` — focused input border color.

- Success / Danger
  - `#cfe9c9` — success icon border.
  - `#8fd08b` — success green used in success icon.
  - `#dc3545` — `.logout-btn` background.
  - `#bb2d3b` — `.logout-btn:hover` background.
  - `#ff4d4f` — `.btn-danger-outline` border & color.

- Overlay / opacity
  - `rgba(0, 0, 0, 0.35)` — login success overlay background.
  - `rgba(255, 255, 255, 0.12)` — `.navbar-links` hover background.

Notes and recommendations

- Do not change the values above directly in the CSS files unless you coordinate with design/UX. These colors are reused across multiple components; changing one may break visual consistency.
- If you want a theme change, first replace these hard-coded values with CSS variables (declare them in [public/css/style.css](public/css/style.css#L1)) then update components to use the variables.
- For quick edits, modify only component-specific colors (e.g., temporary badges) and avoid changing the brand blues, navbar background, logout button red, success green, or body background.

Created to document protected styles referenced by [public/dashboard.php](public/dashboard.php#L1).
