# Valet Development

## Prerequisites

- Node.js

## Setup

```bash
npm install
```

## Build

Build both TypeScript and SCSS:

```bash
npm run build
```

Build individually:

```bash
npm run build:ts
npm run build:css
```

## Watch

Watch both TypeScript and SCSS for changes:

```bash
npm run watch
```

Watch individually:

```bash
npm run watch:ts
npm run watch:css
```

## Source Structure

- `src/ts/valet.ts` → `js/valet.js` (esbuild, minified, source maps)
- `src/scss/valet.scss` → `css/valet.css` (sass, compressed, source maps)
