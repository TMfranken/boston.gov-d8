# Boston.gov Metrolist v2

## Installing

```shell
yarn install
yarn sync-icons
yarn start
```

`yarn start` runs:
  1. `ipconfig getifaddr en6` (or `ipconfig getifaddr en0` if `en6` isn’t found), which determines which LAN IP to bind to. This allows testing on mobile devices connected to the same network.
  2. `webpack-dev-server`. This compiles the ES6+ JavaScript and starts an HTTP server on port 8080 at the address found in the previous step.

Note: The `ipconfig` command has only been tested on a Mac, and it also may not work if your connection isn’t located at `en6` or `en0`.

## General Naming Conventions

### DAMP (Descriptive And Meaningful Phrases).

Prefer readability for other developers over less typing for yourself.

#### Examples

##### HTML/CSS:

```html
<h2 class="sh">Section Header</h2><!-- Bad -->
<h2 class="section-header">Section Header</h2><!-- Good -->
```

##### JavaScript:

```js
const newElCmpShrtNm = 'Header'; // Bad
const newElementComponentShortName = 'Header'; // Good
```

## Programming Conventions

Use Functional Programming principals as often as possible to aid maintainability and predictability. The basic idea is for every function to produce the same output for a given set of inputs regardless of when/where/how often they are called. This means a preference for functions taking their values from explicit parameters as opposed to reading variables from the surrounding scope. Additionally, a function should not produce side-effects by e.g. changing the value of a variable in the surrounding scope.

## CSS Conventions

### Namespacing

All classes namespaced as `ml-` for Metrolist to avoid collisions with main site and/or third-party libraries.

### BEM Methodology

Vanilla BEM (Block-Element-Modifier):
- Blocks: Lowercase name (`block`)
- Elements: two underscores appended to block (`block__element`)
- Modifiers: two dashes appended to block or element (`block--modifier`, `block__element--modifier`).

When writing modifiers, ensure the base class is also present; modifiers should not mean anything on their own. This also gives modifiers higher specificity than regular classes, which helps ensure that they actually get applied.
```scss
.block--modifier {} // Bad
.block.block--modifier {} // Good
```

An exception to this would be for mixin classes that are intended to be used broadly. For example, responsive utilities to show/hide elements at different breakpoints:
```scss
.ml-hide-until-large {
  display: none;
}
@media screen and (min-width: $large) {
  .ml-hide-until-large {
    display: inline-block;
    display: unset;
  }
}
```

Don’t reflect the expected DOM structure in class names, as this expectation is likely to break as projects evolve. Only indicate which block owns the element. This allows components to be transposable and avoids extremely long class names.
```scss
.ml-block__element__subelement {} // Bad
.ml-block__subelement {} // Good
```

#### BEM within Sass

Avoid parent selectors when constructing BEM classes. This allows the full selector to be searchable in IDEs. (Though there is a VS Code extension, [CSS Navigation](https://marketplace.visualstudio.com/items?itemName=pucelle.vscode-css-navigation), that solves this problem, we can’t assume everyone will have it or VS Code installed.)
```scss
.ml-block {
  &__element {} // Bad
}
.ml-block {}
.ml-block__element {} // Good
```

### Sass

Always include parentheses when calling mixins, even if they have no arguments.

```scss
@mixin mixin() {
  // …
}
@include mixin; // Bad
@include mixin(); // Good
```

### Spacing

Don’t declare margins directly on components, only in wrappers.

#### Resources
- [Margin considered harmful](https://mxstbr.com/thoughts/margin)
- [The Stack](https://absolutely.every-layout.dev/layouts/stack/)
- [Braid Design System](https://seek-oss.github.io/braid-design-system/foundations/layout)

### Postprocessing

[Rucksack](https://www.rucksackcss.org/) is installed to enable the same CSS helper functions (such as `font-size: responsive 16px 24px`) that are used on Patterns.

## Build Process

### Module Resolution

Aliases exist to avoid long pathnames, e.g. `import '@components/Foo'` instead of `import '../../../components/Foo'`. Any time an alias is added or removed, three configuration files have to be updated: `webpack.config.js` for compilation, `jest.config.js` for testing, and `.eslintrc.js` for linting. Each one has a slightly different syntax but they all boil down to JSON key-value pairs of the form [alias] → [full path]. Here are the same aliases defined across all three configs:

`webpack.config.js`:
```js
module.exports = {
  "resolve": {
    "alias": {
      "@patterns": path.resolve( __dirname, 'patterns' ),
      "@util": path.resolve( __dirname, 'src/util' ),
      "@globals": path.resolve( __dirname, 'src/globals' ),
      "@components": path.resolve( __dirname, 'src/components' ),
      "__mocks__": path.resolve( __dirname, '__mocks__' ),
    },
  }
};
```

`jest.config.js`:
```js
module.exports = {
  "moduleNameMapper": {
    "^@patterns/(.*)": "<rootDir>/patterns/$1",
    "^@util/(.*)": "<rootDir>/src/util/$1",
    "^@globals/(.*)$": "<rootDir>/src/globals/$1",
    "^@components/(.*)$": "<rootDir>/src/components/$1",
    "^__mocks__/(.*)$": "<rootDir>/__mocks__/$1",
    "\\.(css|s[ca]ss|less|styl)$": "<rootDir>/__mocks__/styleMock.js",
  },
};
```

`.eslintrc.js`:
```js
module.exports = {
  "settings": {
    "import/resolver": {
      "alias": {
        "map": [
          ["@patterns", "./patterns"],
          ["@util", "./src/util"],
          ["@globals", "./src/globals"],
          ["@components", "./src/components"],
          ["__mocks__", "./__mocks__"]
        ],
        "extensions": [".js", ".scss"],
      },
    },
  },
};
```

### Development

Run `yarn build:dev`. Currently this is used for previewing on Netlify, to get a live URL up without going through the lengthy Travis and Acquia build process.

### Production

Run `yarn build:prod`, which first runs a production Webpack build (referencing `webpack.config.js`), then copies the result of that build to `../boston.gov-d8/docroot/modules/custom/bos_components/modules/bos_web_app/apps/metrolist/`, replacing whatever was there beforehand. This requires you to have the [`boston.gov-d8`](https://github.com/CityOfBoston/boston.gov-d8) repo checked out and up-to-date one directory up from the project root.

To make asset URLs work both locally and on Drupal, all references to `/images/` get find-and-replaced to `https://assets.boston.gov/icons/metrolist/` when building for production. Note that this requires assets to be uploaded to `assets.boston.gov` first, by someone with appropriate access. If you want to look at a production build without uploading to `assets.boston.gov` first, you can run a staging build instead.

### Staging

Run `yarn build:stage`. This is identical to the production build, except Webpack replaces references to `/images/` with `/modules/custom/bos_components/modules/bos_web_app/apps/metrolist/images/`. This is where images normally wind up when running `yarn copy:drupal`.

## Interfacing with Main Site

- All `mailto:` links require the class `hide-form` to be set, otherwise they will trigger the generic feedback form.

## Testing API integrations locally

You have to run a browser without CORS restrictions enabled. For Chrome on macOS, you can add this to your `~/.bash_profile`, `~/.zshrc`, or equivalent for convenience:

```shell
alias chrome-insecure='open -n -a Google\ Chrome --args --disable-web-security --user-data-dir=/tmp/chrome --disable-site-isolation-trials --allow-running-insecure-content'
```

This will prevent you from running your normal Chrome profile. To run both simultaneously, install an alternate Chrome such as Canary or Chromium. For Canary you would use this command instead:

```shell
alias chrome-insecure='open -n -a Google\ Chrome\ Canary --args --disable-web-security --user-data-dir=/tmp/chrome --disable-site-isolation-trials --allow-running-insecure-content'
```

Then in a terminal, just type `chrome-insecure` and you will get a separate window with no security and no user profile attached. Sometimes Google changes the necessary commands to disable security, so check around online if this command doesn’t work for you. Unfortunately no extensions will be installed for this profile, and if you install any they will only exist for that session since your data directory is under `/tmp/`.

## Google Translate Compatibility

We’re using React Router for routing, which provides a `Link` component to use in place of `a`. `Link` uses `history.pushState` under the hood, but this will fail inside the Google Translate iframe due to cross-domain security features in the browser. So in order to make app navigation work again, we have to hack around the issue like so:

- Change `base.href` to the Google Translate iframe domain,
- Perform the navigation,
- Change `base.href` back to boston.gov immediately afterward to make sure normal links and assets don’t break.

To do this automatically, there is a custom Metrolist `Link` which wraps the React Router `Link` and attaches a click handler with the workaround logic. So, anytime you want to use React Router’s `Link`, you need to import and use `@components/Link` instead. This is the technique used by the Search component to link to the different pages of results.

If instead you want to use React Router’s `history.push` (or the browser-native `history.pushState`) manually, you can import these helper functions individually:

```js
import {
  switchToGoogleTranslateBaseIfNeeded,
  switchBackToMetrolistBaseIfNeeded,
} from '@util/translation';
import { useHistory } from 'react-router-dom';

const history = useHistory();

switchToGoogleTranslateBaseIfNeeded();

history.push( newUrlPath );

switchBackToMetrolistBaseIfNeeded();
```

This is the technique used by the AMI Estimator component to link to navigate between the different steps in the form.