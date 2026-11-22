# Pitan Portfolio 2027

PHP templates + JSON data → static HTML. PHP runs only during the build; the published site contains no PHP runtime.

## Local build

Requires PHP 8.3+.

```sh
php build.php
```

Open `dist/index.html` in a browser.

## Content

- `data/site.json`: profile, links, skills
- `data/projects.json`: selected public projects
- `templates/index.php`: HTML template
- `assets/`: styles and client-side scripts
- `build.php`: static site generator

## Deploy

GitHub Actions builds on pushes to `main` and deploys `dist/` to GitHub Pages. Enable GitHub Pages with **GitHub Actions** as the build and deployment source in repository settings.

Only public-facing Pitan identity and projects are included. Do not add legal-name activity, personal identifiers, or links that associate anonymous and real-name identities.
