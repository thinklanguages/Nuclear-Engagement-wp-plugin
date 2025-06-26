# Translation Guide

Guidelines for translating Nuclear Engagement into other languages.

This plugin ships with a translation template located at `nuclear-engagement/languages/nuclear-engagement.pot`.
Follow the steps below to create or update `.po` and `.mo` files for your locale.

## Creating a New Translation

1. Open the POT file in Poedit (or any gettext editor).
2. Choose **Create new translation** and select your language.
3. Translate the strings and save the file as `nuclear-engagement-<locale>.po`.
4. Poedit will automatically compile the corresponding `nuclear-engagement-<locale>.mo` file.
5. Copy both files to the plugin's `languages/` directory.

WordPress will load translations from this folder when the site language matches the locale code.

## Updating the POT File

After adding new translatable strings in the plugin code, regenerate the template using WP‑CLI. Run the command from the repository root so all plugin files are scanned:

```bash
wp i18n make-pot nuclear-engagement nuclear-engagement/languages/nuclear-engagement.pot
```

You can also use the helper script defined in `package.json`:

```bash
npm run i18n
```

Then refresh your `.po` files in Poedit ("Update from POT") and recompile the `.mo` files.
