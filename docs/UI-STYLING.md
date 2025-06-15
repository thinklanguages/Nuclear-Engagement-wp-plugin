# UI Styling Guidelines

Follow these conventions when creating styles for Nuclear Engagement's admin and front-end interfaces.

## Namespacing

- Load a single, prefixed CSS file for each context such as `ne-admin.css` or `ne-frontend.css` to avoid conflicts with themes or other plugins.

## CSS Custom Properties

- Expose variables like `--ne-color-primary` and `--ne-spacing-sm` so host themes can override values without editing plugin files.

## Respect the Theme

- Inherit `font-family`, `line-height`, and link styles from the active theme unless a component truly needs its own declarations.

## BEM + Utility Blend

- Structure component classes as `.ne-block__item--state` and supplement with concise utilities such as `.u-mt-md`.

## Responsive via Container Queries

- Adjust plugin blocks based on their own width using container queries instead of relying on the viewport.

## WordPress Colour Palette

- Map accent colours to the WordPress global palette API so users can change them in the editor.

