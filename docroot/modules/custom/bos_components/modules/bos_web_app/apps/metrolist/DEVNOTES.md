# Development Notes

## Metrolist React Buildout (Exploratory)

- Was hotlinking to `patterns.boston.gov/public.css` (see below). But Storybook can’t (AFAIK) bring in remote CSS files, so the components appeared unstyled.
  ```html
  <!--[if !IE]><!-->
  <link rel="stylesheet" type="text/css" href="https://patterns.boston.gov/css/public.css" />
  <!--<![endif]-->
  <!--[if lt IE 10]>
    <link media="all" rel="stylesheet" href="https://patterns.boston.gov/css/ie.css">
  <![endif]-->
  ```
- Attempted solution: bring in `CityOfBoston/patterns` as a Git submodule and compile it inside of React and Storybook.
- Unfortunately, Create React App (CRA) does not allow configuration of Webpack, which means we can’t easily add Stylus compilation.
- Workaround: render Stylus files using `create-react-app-stylus`, which generates a single master Stylus file into a CSS file that can be imported into React.
- Had Patterns submodule at `patterns/`. But CRA requires all imports to be under `src/`. So, moved `patterns/` to `src/patterns/`.
- Unfortunately this also brings in all the TypeScript files, which causes CRA to type-check these files which are ultimately irrelevant to this project. So had to add `typescript`, `@types/lodash`, `testcafe`, `@stencil/core`, `vega-lib`, `vega-lite`, `vega-tooltip`.
- So many compilation errors due to type-checking. So copy-and-pasted `tsconfig.json` from Patterns to Metrolist root directory to hopefully alleviate.
- Too many errors. Getting out of hand. Maybe there’s a way to convert Stylus into SCSS (Sass).
  - [stylus-converter](https://github.com/txs1992/stylus-converter)
    - Mostly works?
      ```shell
      Failed to convert patterns/stylesheets/grid/modern/_grid.styl
      Failed to convert patterns/stylesheets/shame/grid/_grid.styl
      ```
- Maybe we eject from CRA since the final project probably has already?
  - React imports example: `boston.gov-d8/docroot/modules/custom/bos_components/modules/bos_web_app/bos_web_app.libraries.yml`.
    - No signs of CRA.
  - Ejecting…
- The Drupal site only uses Babel, not Webpack. Would have to add for Drupal integration.
  - Looks like the relevant package.json: `boston.gov-d8/docroot/core/package.json`.
- Ejecting was also a disaster because it gives you an insane Webpack config. Manually uninstalling react-scripts and setting up Webpack/Babel: `yarn add @babel/core @babel/preset-env @babel/preset-react webpack webpack-cli webpack-dev-server babel-loader css-loader style-loader html-webpack-plugin`. (via [tutorial](https://dev.to/vish448/create-react-project-without-create-react-app-3goh))
- Create React App had its own way of resolving `%PUBLIC_URL%` in `public/index.html`. Adding to dotenv and calling from `htmlWebpackPlugin.options.PUBLIC_URL`.
- Trying to compile `patterns/stylesheets/public.styl` using stylus-loader hangs Webpack. The process runs out of memory!
  - Looks like the culprits are:
    - `@require 'base/**/**/base.styl'`, which has `@require('*.styl')`.
    - `@require 'grid/modern/base.styl';`, which has `@require('*.styl')`.
    - …basically, anything with excessive globbing?
      - Tried to switch from `**/**` (why double?) to just `**` but it didn’t help. This is too much work; abandoning.
- New approach: download [precompiled stylesheet](https://patterns.boston.gov/css/public.css) for existing components.
  - public.css has unresolved variables in it??
  - Relative file URLs get messed up if `public.css` is outside of the patterns directory structure.
    - Couldn’t find an obvious way to map URLs to outside the folder via Webpack.
    - Workaround: download `public.css` to same directory as `public.styl`. In `.gitmodules`, set `ignore = dirty`. Now the addition of `public.css` won’t affect commits.
- Replacing hard-coded image paths in `src/components/Layout/index.js` with React imports to include in Webpack build process. Otherwise, images are broken.
- SVGs referenced in `public.css` need a Webpack loader to resolve. Using `file-loader`.
- Aliasing `patterns/` to `@patterns/` in imports to avoid doing `../../..` since it sits outside of the `src` directory.
  - This is done in `webpack.config.js` but has to be mirrored in `.eslintrc.js` in order to silence ESLint errors.
- Icons are on S3 at `https://assets.boston.gov/icons/accessboston/` which is the single source of truth. So probably shouldn’t bring in to local Webpack pipeline.
  - SVG assets only, no PNGs.
    - Fallbacks necessary?
  - Setting up an icon syncing script: S3 → icons_manifest.json.
    - Can check if an icon is valid via PropTypes, but don’t want to pull in such a large JSON object (319 KB file w/o minifying) in production. Using Webpack will conditionally load the object into an `__ICONS_MANIFEST__` global.
      - This proved very difficult! For some reason, `process.env.NODE_ENV` is not set at the time that `webpack.config.js` is read. Have to do ALL of the following:
        - Call `webpack-dev-server --open --env`. `--env` allows you to export an environment variable to your `webpack.config.js` using a function expression: `module.exports = ( env ) => {}`. If your `module.exports` is just a JSON structure, it can’t be fully dynamic. Normally you would do `--env.FOO_BAR=baz` but hanging conditions on an argument defeats the point of using a `.env` file.
        - `require( 'dotenv' ).config();`. This SHOULD pull in everything from `.env`, but for some reason some key(s) were missing.
        - `const Dotenv = require( 'dotenv-webpack' );` + `plugins: [ new Dotenv(), ]`. This filled in the gaps in the `.env` import.
        - After toggling a bunch/researching:
          - `process.env.NODE_ENV` would be replaced globally regardless of whether dotenv or dotenv-webpack were loaded. That’s because Webpack is setting it independently, which can be turned off via `optimization.nodeEnv = false`.
          - Loading `require( 'dotenv' ).config()` sets `process.env.*` in the Webpack config context *only*. Not passed onto React!
          - Loading `const Dotenv = require( 'dotenv-webpack' );` + `plugins: [ new Dotenv(), ]` sets `process.env.*` in the React context *only*! Not available in Webpack config!
          - Thus, using both allows `process.env.*` (as defined in .env) to be global!
          - We actually don’t need `--env` passed to `webpack-dev-server` as that simply sets a variable called `env` to `true`. And thus, we don’t need `module.exports` to be a function. …what a journey.
    - Hot reloading breaks; possible that `__ICONS_MANIFEST__` is too large.
      - Maybe we skip the local sync and just do a `fetch` with caching if on dev.
        - Hybrid approach: Still use the sync script, but par down redundant keys and minify. Use a dynamic `import()` for the generated JSON in the [class-ified] React component that calls setState on Promise resolution and re-renders the icon component which now has a `src`.
          - There is a visible lag as the images are lazy-loaded. Does this matter? For production, perhaps static analysis could slot in the appropriate URLs? Might be getting too complex though…

## Fix Storybook

- Since getting out of Create React App, Storybook broke.
- Disabled CRA plugin.
- Storybook can’t parse special paths such as the aliased `@patterns/`.
- Storybook has its own Webpack config (`./.storybook/webpack.config.js`) and doesn’t read from the root config (`./webpack.config.js`) by default.
- Attempting to extend the exist Webpack also failed.
  - Actually it was just irrelevant config keys screwing it up. Imported the root config and then deleted everything except "resolve" and "module".
  - Still getting this but it’s non-blocking:
  ```
  DeprecationWarning: Extend-mode configuration is deprecated, please use full-control mode instead.
  ```

## Outline Drupal vs React portions

From Jim:
> Subscription,
> Feedback,
> Metrolist home (add custom header work),
> Affordable Housing page,
> Income Restricted guide page,
> and the lower components on the React pages.

## Find and copy existing Drupal components

- For some reason my boston.gov-d8 repo did not have Drupal fully installed. Did `lando drupal-sync-db` and got:
  ```
  $ lando drupal-sync-db
  /app/scripts/local/sync.sh: line 9: printout: command not found
  You will destroy data in drupal and replace with data from bostond8dev.ssh.prod.acquia-sites.com/bostond8dev.

  // Do you really want to continue?: yes.

  [notice] Starting to dump database on source.
  [notice] Starting to discover temporary files directory on target.
  [notice] Copying dump file from source to target.
  [notice] Starting to import dump file onto target database.
  /app/hooks/common/cob_utilities.sh: line 99: @self: command not found
  [action] Update database (drupal) on local with configuration from updated code in current branch.
  /app/hooks/common/cob_utilities.sh: line 104: @self: command not found
  /app/hooks/common/cob_utilities.sh: line 109: @self: command not found
  /app/hooks/common/cob_utilities.sh: line 134: @self: command not found
  [action] Enable DEVELOPMENT-ONLY modules.
  /app/hooks/common/cob_utilities.sh: line 319: cdel: command not found
  /app/hooks/common/cob_utilities.sh: line 320: cdel: command not found
  /app/hooks/common/cob_utilities.sh: line 321: en: command not found
  [action] Enable and set stage_file_proxy.
  /app/hooks/common/cob_utilities.sh: line 327: en: command not found
  [action] Disable Acquia connector and purge.
  /app/hooks/common/cob_utilities.sh: line 361: pmu: command not found
  [action] Disable prod-only and unwanted modules.
  /app/hooks/common/cob_utilities.sh: line 366: pmu: command not found
  /app/hooks/common/cob_utilities.sh: line 374: pmu: command not found
  [notice] simplesamlphp_auth module is disabled for local builds.
            If you need to configure this module you will first need to enable it and then
            run 'lando drush cim' to import its configurations.
  /app/hooks/common/cob_utilities.sh: line 270: cset: command not found
  /app/hooks/common/cob_utilities.sh: line 271: cset: command not found
  /app/hooks/common/cob_utilities.sh: line 272: cset: command not found
  [success] Changed password for admin.
  INFOSUCCESS
  ```
  Running `lando start` booted up to an unusable state:
  ```
  The website encountered an unexpected error. Please try again later.

  Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException: The "menu" entity type did not specify a storage handler. in Drupal\Core\Entity\EntityTypeManager->getHandler() (line 272 of core/lib/Drupal/Core/Entity/EntityTypeManager.php).
  Drupal\Core\Entity\EntityTypeManager->getStorage('menu') (Line: 541)
  Drupal\Core\Entity\EntityBase::loadMultiple() (Line: 443)
  menu_ui_get_menus() (Line: 341)
  bos_theme_preprocess_page(Array, 'page', Array) (Line: 287)
  Drupal\Core\Theme\ThemeManager->render('page', Array) (Line: 431)
  Drupal\Core\Render\Renderer->doRender(Array, ) (Line: 200)
  Drupal\Core\Render\Renderer->render(Array) (Line: 501)
  Drupal\Core\Template\TwigExtension->escapeFilter(Object, Array, 'html', NULL, 1) (Line: 104)
  __TwigTemplate_1481512973d57358bf6a7e3ee1621dfaf106e5042378e7bb403d8a3fbe8de610->doDisplay(Array, Array) (Line: 455)
  Twig\Template->displayWithErrorHandling(Array, Array) (Line: 422)
  Twig\Template->display(Array) (Line: 434)
  Twig\Template->render(Array) (Line: 64)
  twig_render_template('themes/custom/bos_theme/templates/layout/html.html.twig', Array) (Line: 384)
  Drupal\Core\Theme\ThemeManager->render('html', Array) (Line: 431)
  Drupal\Core\Render\Renderer->doRender(Array, ) (Line: 200)
  Drupal\Core\Render\Renderer->render(Array) (Line: 147)
  Drupal\Core\Render\MainContent\HtmlRenderer->Drupal\Core\Render\MainContent\{closure}() (Line: 573)
  Drupal\Core\Render\Renderer->executeInRenderContext(Object, Object) (Line: 148)
  Drupal\Core\Render\MainContent\HtmlRenderer->renderResponse(Array, Object, Object) (Line: 90)
  Drupal\Core\EventSubscriber\MainContentViewSubscriber->onViewRenderArray(Object, 'kernel.view', Object)
  call_user_func(Array, Object, 'kernel.view', Object) (Line: 111)
  Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher->dispatch('kernel.view', Object) (Line: 156)
  Symfony\Component\HttpKernel\HttpKernel->handleRaw(Object, 1) (Line: 68)
  Symfony\Component\HttpKernel\HttpKernel->handle(Object, 1, 1) (Line: 57)
  Drupal\Core\StackMiddleware\Session->handle(Object, 1, 1) (Line: 47)
  Drupal\Core\StackMiddleware\KernelPreHandle->handle(Object, 1, 1) (Line: 106)
  Drupal\page_cache\StackMiddleware\PageCache->pass(Object, 1, 1) (Line: 85)
  Drupal\page_cache\StackMiddleware\PageCache->handle(Object, 1, 1) (Line: 49)
  Asm89\Stack\Cors->handle(Object, 1, 1) (Line: 50)
  Drupal\ban\BanMiddleware->handle(Object, 1, 1) (Line: 47)
  Drupal\Core\StackMiddleware\ReverseProxyMiddleware->handle(Object, 1, 1) (Line: 52)
  Drupal\Core\StackMiddleware\NegotiationMiddleware->handle(Object, 1, 1) (Line: 23)
  Stack\StackedHttpKernel->handle(Object, 1, 1) (Line: 694)
  Drupal\Core\DrupalKernel->handle(Object) (Line: 19)
  ```
  Probably due to missing commands?.
  - [Completely starting over](https://docs.boston.gov/digital/guides/drupal-8/installation-instructions/lando-101#lando-workflows): `lando destroy`, `rm -rf`, `git clone`, `lando start`. This produced the following:
  ```
  Fatal error: Maximum execution time of 90 seconds exceeded in /app/vendor/symfony/yaml/Inline.php on line 631 Call Stack: 0.0032 412032 1. {main}() /app/docroot/index.php:0 0.0578 579720 2. Drupal\Core\DrupalKernel->handle() /app/docroot/index.php:19 0.1408 2298752 3. Stack\StackedHttpKernel->handle() /app/docroot/core/lib/Drupal/Core/DrupalKernel.php:694 0.1408 2298752 4. Drupal\Core\StackMiddleware\NegotiationMiddleware->handle() /app/vendor/stack/builder/src/Stack/StackedHttpKernel.php:23 0.1409 2299448 5. Drupal\Core\StackMiddleware\ReverseProxyMiddleware->handle() /app/docroot/core/lib/Drupal/Core/StackMiddleware/NegotiationMiddleware.php:52 0.1409 2299448 6. Drupal\ban\BanMiddleware->handle() /app/docroot/core/lib/Drupal/Core/StackMiddleware/ReverseProxyMiddleware.php:47 0.1420 2299448 7. Asm89\Stack\Cors->handle() /app/docroot/core/modules/ban/src/BanMiddleware.php:50 0.1421 2299448 8. Drupal\page_cache\StackMiddleware\PageCache->handle() /app/vendor/asm89/stack-cors/src/Asm89/Stack/Cors.php:49 0.1430 2302064 9. Drupal\page_cache\StackMiddleware\PageCache->lookup() /app/docroot/core/modules/page_cache/src/StackMiddleware/PageCache.php:82 0.1442 2302128 10. Drupal\page_cache\StackMiddleware\PageCache->fetch() /app/docroot/core/modules/page_cache/src/StackMiddleware/PageCache.php:128 0.1442 2302128 11. Drupal\Core\StackMiddleware\KernelPreHandle->handle() /app/docroot/core/modules/page_cache/src/StackMiddleware/PageCache.php:191 0.3518 2592808 12. Drupal\Core\StackMiddleware\Session->handle() /app/docroot/core/lib/Drupal/Core/StackMiddleware/KernelPreHandle.php:47 0.3567 2690728 13. Symfony\Component\HttpKernel\HttpKernel->handle() /app/docroot/core/lib/Drupal/Core/StackMiddleware/Session.php:57 0.3568 2691144 14. Symfony\Component\HttpKernel\HttpKernel->handleRaw() /app/vendor/symfony/http-kernel/HttpKernel.php:68 33.8159 57694824 15. Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher->dispatch() /app/vendor/symfony/http-kernel/HttpKernel.php:156 33.8641 57774304 16. call_user_func:{/app/docroot/core/lib/Drupal/Component/EventDispatcher/ContainerAwareEventDispatcher.php:111}() /app/docroot/core/lib/Drupal/Component/EventDispatcher/ContainerAwareEventDispatcher.php:111 33.8642 57774304 17. Drupal\Core\EventSubscriber\MainContentViewSubscriber->onViewRenderArray() /app/docroot/core/lib/Drupal/Component/EventDispatcher/ContainerAwareEventDispatcher.php:111 33.8817 57831144 18. Drupal\Core\Render\MainContent\HtmlRenderer->renderResponse() /app/docroot/core/lib/Drupal/Core/EventSubscriber/MainContentViewSubscriber.php:90 33.8818 57831144 19. Drupal\Core\Render\MainContent\HtmlRenderer->prepare() /app/docroot/core/lib/Drupal/Core/Render/MainContent/HtmlRenderer.php:117 113.2583 95282376 20. Drupal\block\Plugin\DisplayVariant\BlockPageVariant->build() /app/docroot/core/lib/Drupal/Core/Render/MainContent/HtmlRenderer.php:259 113.2583 95283152 21. Drupal\block\BlockRepository->getVisibleBlocksPerRegion() /app/docroot/core/modules/block/src/Plugin/DisplayVariant/BlockPageVariant.php:137 113.3294 95302296 22. Drupal\block\Entity\Block->access() /app/docroot/core/modules/block/src/BlockRepository.php:56 113.3421 95305056 23. Drupal\block\BlockAccessControlHandler->access() /app/docroot/core/lib/Drupal/Core/Entity/EntityBase.php:370 113.3487 95307480 24. Drupal\block\BlockAccessControlHandler->checkAccess() /app/docroot/core/lib/Drupal/Core/Entity/EntityAccessControlHandler.php:105 113.3733 95316496 25. Drupal\block\Entity\Block->getPlugin() /app/docroot/core/modules/block/src/BlockAccessControlHandler.php:118 113.3733 95316496 26. Drupal\block\Entity\Block->getPluginCollection() /app/docroot/core/modules/block/src/Entity/Block.php:145 113.3771 95318536 27. Drupal\block\BlockPluginCollection->__construct() /app/docroot/core/modules/block/src/Entity/Block.php:156 113.3771 95318536 28. Drupal\block\BlockPluginCollection->__construct() /app/docroot/core/modules/block/src/BlockPluginCollection.php:34 113.3771 95318536 29. Drupal\block\BlockPluginCollection->addInstanceId() /app/docroot/core/lib/Drupal/Core/Plugin/DefaultSingleLazyPluginCollection.php:55 113.3771 95318912 30. Drupal\block\BlockPluginCollection->setConfiguration() /app/docroot/core/lib/Drupal/Core/Plugin/DefaultSingleLazyPluginCollection.php:99 113.3771 95318912 31. Drupal\block\BlockPluginCollection->get() /app/docroot/core/lib/Drupal/Core/Plugin/DefaultSingleLazyPluginCollection.php:83 113.3772 95318912 32. Drupal\block\BlockPluginCollection->get() /app/docroot/core/modules/block/src/BlockPluginCollection.php:45 113.3772 95318912 33. Drupal\block\BlockPluginCollection->initializePlugin() /app/docroot/core/lib/Drupal/Component/Plugin/LazyPluginCollection.php:80 113.3772 95318912 34. Drupal\block\BlockPluginCollection->initializePlugin() /app/docroot/core/modules/block/src/BlockPluginCollection.php:57 113.3772 95318912 35. Drupal\Core\Block\BlockManager->createInstance() /app/docroot/core/lib/Drupal/Core/Plugin/DefaultSingleLazyPluginCollection.php:62 113.3773 95318992 36. Drupal\Core\Plugin\Factory\ContainerFactory->createInstance() /app/docroot/core/lib/Drupal/Component/Plugin/PluginManagerBase.php:76 113.3773 95318992 37. Drupal\Core\Block\BlockManager->getDefinition() /app/docroot/core/lib/Drupal/Core/Plugin/Factory/ContainerFactory.php:16 113.3773 95318992 38. Drupal\Core\Block\BlockManager->getDefinitions() /app/docroot/core/lib/Drupal/Component/Plugin/Discovery/DiscoveryCachedTrait.php:22 113.3786 95318992 39. Drupal\Core\Block\BlockManager->findDefinitions() /app/docroot/core/lib/Drupal/Core/Plugin/DefaultPluginManager.php:175 116.2997 104449608 40. Drupal\Core\Block\BlockManager->processDefinition() /app/docroot/core/lib/Drupal/Core/Plugin/DefaultPluginManager.php:286 116.2998 104449984 41. Drupal\Core\Block\BlockManager->processDefinitionCategory() /app/docroot/core/lib/Drupal/Core/Block/BlockManager.php:67 116.2998 104449984 42. Drupal\Core\Block\BlockManager->getProviderName() /app/docroot/core/lib/Drupal/Core/Plugin/CategorizingPluginManagerTrait.php:34 116.2998 104449984 43. Drupal\Core\Extension\ModuleHandler->getName() /app/docroot/core/lib/Drupal/Core/Plugin/CategorizingPluginManagerTrait.php:52 116.2999 104449984 44. Drupal\Core\Extension\ModuleExtensionList->getName() /app/docroot/core/lib/Drupal/Core/Extension/ModuleHandler.php:751 116.2999 104449984 45. Drupal\Core\Extension\ModuleExtensionList->get() /app/docroot/core/lib/Drupal/Core/Extension/ExtensionList.php:243 116.2999 104449984 46. Drupal\Core\Extension\ModuleExtensionList->getList() /app/docroot/core/lib/Drupal/Core/Extension/ExtensionList.php:260 116.3013 104449984 47. Drupal\Core\Extension\ModuleExtensionList->doList() /app/docroot/core/lib/Drupal/Core/Extension/ExtensionList.php:282 116.3013 104449984 48. Drupal\Core\Extension\ModuleExtensionList->doList() /app/docroot/core/lib/Drupal/Core/Extension/ModuleExtensionList.php:148 148.0860 109469776 49. Drupal\Core\Extension\ModuleExtensionList->createExtensionInfo() /app/docroot/core/lib/Drupal/Core/Extension/ExtensionList.php:316 148.0860 109469776 50. Drupal\Core\Extension\InfoParser->parse() /app/docroot/core/lib/Drupal/Core/Extension/ExtensionList.php:554 148.0860 109469776 51. Drupal\Core\Extension\InfoParser->parse() /app/docroot/core/lib/Drupal/Core/Extension/InfoParser.php:22 148.0906 109471056 52. Drupal\Component\Serialization\Yaml::decode() /app/docroot/core/lib/Drupal/Core/Extension/InfoParserDynamic.php:50 148.0908 109471056 53. Drupal\Component\Serialization\YamlSymfony::decode() /app/docroot/core/lib/Drupal/Component/Serialization/Yaml.php:35 148.0909 109471280 54. Symfony\Component\Yaml\Parser->parse() /app/docroot/core/lib/Drupal/Component/Serialization/YamlSymfony.php:37 148.0909 109471280 55. Symfony\Component\Yaml\Parser->doParse() /app/vendor/symfony/yaml/Parser.php:142 148.1227 109477864 56. Symfony\Component\Yaml\Parser->parseBlock() /app/vendor/symfony/yaml/Parser.php:373 148.1228 109478088 57. Symfony\Component\Yaml\Parser->doParse() /app/vendor/symfony/yaml/Parser.php:517 148.1473 109483872 58. Symfony\Component\Yaml\Parser->parseValue() /app/vendor/symfony/yaml/Parser.php:244 148.1475 109483952 59. Symfony\Component\Yaml\Inline::parse() /app/vendor/symfony/yaml/Parser.php:774 148.1475 109483976 60. Symfony\Component\Yaml\Inline::parseScalar() /app/vendor/symfony/yaml/Inline.php:126 148.1476 109484056 61. Symfony\Component\Yaml\Inline::evaluateScalar() /app/vendor/symfony/yaml/Inline.php:363
  ```

## Fix Sass duplication of code

- `@extend` doesn’t work as intended out-of-the-box with per-component SCSS, because each `@import` brings everything into a separate CSS file (style tag in dev). So having `@extend %placeholder` works exactly the same as `@include .mixin`. Also, having `.sr-only` in `globals/index.scss` which is at the top of every component’s SCSS, brought it in once for each React component.
  - Attempted solutions:
    - https://github.com/webpack-contrib/mini-css-extract-plugin
    - https://github.com/NMFR/optimize-css-assets-webpack-plugin
  - Solution: move all output-producing classes to `globals/util.scss` and import once in `index.js`. May have to revisit for production build.

## Mobile styles for Listings (Search) view

### Resolved

- Text doesn’t match:
  - Desktop: “For Rent, For Sale”
  - Mobile: “Rent, Buy”
- Element functions don’t match:
  - Desktop: Checkbox
  - Mobile: Button
    - <del>Indication of checked/unchecked state is indicated by a border. Nonstandard.</del> <ins>This was only true in the Sketch file; the InVision link corrects this by using blue bg = checked, white bg = unchecked. But the default view shows both as blue bg which makes them appear as buttons.</ins>
    - The different backgrounds for checked/unchecked in button form imply mutual exclusivity, whereas the checkboxes do not.
- The “Apply Filters” button exists on Mobile but not Desktop. Why?
- No need for mobile Unit listings to be all-caps
- Mismatched wording:
  - Desktop: “Search for housing based on your income / Enter basic information to help determine your eligibility for income-restricted housing.”
  - Mobile: “Find housing based on your income / Use the AMI Estimator Tool to find housing”
- Extra content:
  - Desktop: Nothing
  - Mobile: “Learn about income eligibility? / Area Media Income (AMI). Learn about it here.”
- Missing content:
  - Desktop: Metrolist Header
  - Mobile: Nothing
- Selection state should be reflected in mobile dropdown menus. E.g. when you select Income Eligibility > 60% and then collapse the menu, the label should say something like “Income Eligibility: 60%”, maybe with option to clear the selection somehow. Same with master “Filter” dropdown. While they might not all fit, could present e.g. “5 filters applied”.

### Unresolved

- Style of checkbox checks:
  - Desktop: Square
  - Mobile: Checkmark
- Details arrows don’t match [Drawer arrows](https://patterns.boston.gov/components/detail/drawers--default.html) in Fleet

## Row vs. Stack: redundant or useful?

- Only redundant in the sense of `stackUntil`, `toppleUntil`, etc.
- Distinction should be: Stack is only for vertical stacking with `margin-top`. Rows can have columns stacked until a certain breakpoint.
- `toppleUntil` is hard to conceptualize.

## AMI Calculator

- Introducing the calculator creates a second React view, which means we need to route between the two. However since this entire React app will live inside of Drupal, need to make sure the two will play nice since typically routing in SPAs is achieved by sending every HTTP request to index.html.
  - [Embedding a React App in a Drupal 8 Site](https://redfinsolutions.com/blog/embedding-react-app-drupal-8-site)

### Design

- Are we including the headers? Missing in mockup.
- Background for AMI Calculator is white; for Search it’s gray.
- Series of numbered checkboxes seems to replicate the Scale component I created. Can we just swap that one in?
- Wording: Back vs. Next. Should this be Previous vs. Next?
- Back button is light gray on white; is this enough contrast?
  - Also need to know hover state as this is a new button treatment
- Error state of checkboxes is color-only, which is an accessibility concern. Can we add text indicating errors?
- What does the “Exit” link at the bottom do? Is this meant to be a modal window?

## Integrate with Housing/Development API

- Might be a question for Amy, but we need to settle on naming: developments vs homes vs something else.
  On the frontend I called them “homes” because apparently some “developments” aren’t actually developments.
- Prefer units nested under homes vs. flat object
- Prefer unquoted non-string values, to parse as int/bool automatically
- Prefer “ID style” (camelCase, abbreviations if common) for all non-title values. This makes a distinction between the data layer and the language layer, which makes branching and translation efforts cleaner. Although sentence-case identifiers aren’t impossible, they feel off. I.e. instead of:
  ```json
  {
    "type": "Rent",
    "unitType": "Single Room Occupancy",
    "userGuidType": "Lottery",
  }
  ```
  Could we make it:
  ```json
  {
    "type": "rent",
    "unitType": "sro",
    "userGuidType": "lottery"
  }
  ```
  Example Use Case - Before:
  ```jsx
  const localizations = {
    "en": {
      "Single Room Occupancy": "Single Room Occupancy",
    },
    "es": {
      "Single Room Occupancy": "Ocupación de habitación individual"
    }
  };
  ```
  Example Use Case - After:
  ```jsx
  const localizations = {
    "en": {
      "sro": "Single Room Occupancy",
    },
    "es": {
      "sro": "Ocupación de habitación individual"
    }
  };
  ```
- City and neighborhood are embedded in development name while being repeated in other fields. Also sometimes have inconsistent capitalization. Instead of:
  ```json
  {
    "development": "2424 Boylston st Boston - Fenway",
    "city": "Boston",
    "neighborhood": "Fenway"
  }
  ```
  Could we make it:
  ```json
  {
    "development": "2424 Boylston St",
    "city": "Boston",
    "neighborhood": "Fenway"
  }
- Proposed field/value changes:
  ```js
  {
    // development → title
    // developmentID → id
    // We already know we have a development object,
    // and by nesting the units we won’t have clashes for the same key names.
    "title": "45 Lothrop Street Beverly -",
    "id": "11566046",

    // developmentURI → slug; remove leading slash
    //                    OR
    //                  url; keep leading slash
    // Avoids confusion between “URL” and “URI”; this can be appended to the base URL to get the permalink.
    "slug": "45-lothrop-street-beverly",

    // Remove developmentURL
    // The base URL is the same one we have already connected to in order to make the API request,
    // so we can simply reference the relevant variable in the outer scope.
    // Also, this domain name seems to be the same across all developments,
    // so there is no need to repeat it for each one.
    // "developmentURL": "https:\/\/d8-dev2.boston.gov\/45-lothrop-street-beverly",

    // region → cardinalDirection; remove “of Boston”; lowercase value
    // “Region” can have many different meanings depending on context, so this avoids confusion.
    // Since this field is only used to indicate a city’s relative position to Boston,
    // and takes N/E/S/W values, it can be more accurately described as a cardinal direction.
    // Before: "region": "North of Boston",
    "cardinalDirection": "north", // or null for ( city === Boston )

    "city": "Beverly",

    // Is it possible for a location in Boston to be outside of any neighborhood?
    // Or is this simply a field someone didn’t fill out?
    // Bringing this in on the UI side created blank spaces.
    // Can filter empty values but feels odd for neighborhood to be variably specified.
    "neighborhood": "", 

    // type → offer; lowercase value
    // Possible values: rent|own → rent|sale
    // The word “type” is generic and should be avoided if a more descriptive label can be used.
    // Also, the language on the site is currently “For Rent” and “For Sale”, so let’s match that.
    "offer": "rent",

    // unitType → type; lowercase value; Possible values: apt|sro|condo|etc
    // With “type” freed up from the rename to “offer”,
    // we can use it to describe the kind of building we are dealing with.
    "type": "sro",

    // Move beds, ami, and price to new array field named "units". Convert all to numeric values.
    // De-duplicate developments while placing all respective units in this array.
    "units": [
      {
        // beds → bedrooms
        // This maps to the phrasing used on the site.
        "bedrooms": 0,

        // ami → amiQualification
        // Just to make it clear that this isn’t referring to any of the other definitions of AMI.
        "amiQualification": 50,

        "price": 800,

        // Add a "priceRate" field so we know whether it’s recurring or not. Possible values: monthly|once.
        // I realize we can infer this from the type field being an apartment or not but this makes it explicit.
        "priceRate": "monthly"
      }
    ],

    // Convert to boolean
    "incomeRestricted": true,

    // userGuidType → assignment; lowercase value
    // The current name is confusing; makes me think every user has a
    // Globally Unique Identifier and there are multiple ways of generating one?
    // Would not expect to find “Lottery” as a value here. 
    // Possible values: lottery|first|waitlist|etc.
    "assignment": "lottery",
    
    // Remove openWaitlist
    // Whether the allocation is by open waitlist or not can already be determined by the assignment field. 
    // "openWaitlist": "false",

    // posted → listingDate
    // “Posted” sounds like it could be a boolean field.
    // We also want all date fields to be named the same way,
    // e.g. posted and appDueDate; both date fields but the naming doesn’t match.
    //
    // Would also prefer to operate on UTC internally and convert to the user’s time zone
    // on display, rather than encoding the time zone offset in the data, which might change.
    // See below for the regex I’m using to validate dateTime.
    // Before: "listingDate": "2020-04-22T14:38:55-0400",
    "listingDate": "2020-04-22T14:38:55Z",
    
    // Remove postedTimeAgo
    // I am already calculating this in JavaScript based on the time the user accesses the page.
    // If we pre-calculate it on the backend then the value will likely fall out of date due to caching.
    // "postedTimeAgo": "1 day ago",

    // appDueDate → applicationDueDate; remove time portion
    // “App” sounds like a piece of software.
    // And because the application will always be due at 11:59:59 PM, there’s no need to specify the time.
    // Before: "applicationDueDate": "2020-04-30T12:00:00",
    "applicationDueDate": "2020-04-30",

    // Remove appDueDateTimeAgo for the same reason as postedTimeAgo
    // "appDueDateTimeAgo": "in 6 days"
  }

  // Follows the XML Schema type definitions, which are ISO 8601–compliant but don’t cover 100% of it.
  // Regex via: https://www.oreilly.com/library/view/regular-expressions-cookbook/9781449327453/ch04s07.html
  const dateRegex = /^(-?(?:[1-9][0-9]*)?[0-9]{4})-(1[0-2]|0[1-9])-(3[01]|0[1-9]|[12][0-9])(Z|[+-](?:2[0-3]|[01][0-9]):[0-5][0-9])?$/;
  // Not used currently but: const timeRegex = /^(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])(\.[0-9]+)?(Z|[+-](?:2[0-3]|[01][0-9]):[0-5][0-9])?$/;
  const dateTimeRegex = /^(-?(?:[1-9][0-9]*)?[0-9]{4})-(1[0-2]|0[1-9])-(3[01]|0[1-9]|[12][0-9])T(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])(\.[0-9]+)?(Z|[+-](?:2[0-3]|[01][0-9]):[0-5][0-9])?$/;
  ```

API Endpoints:
  - AMI Estimator: https://d8-dev2.boston.gov/metrolist/api/v1/ami/hud/base?_format=json
  - Search: https://d8-dev2.boston.gov/metro/api/v1/units?_format=json
  - Production versions would be at boston.gov.

## Search page not loading in Safari/Edge/Internet Explorer

### Safari

Unknown cause, seems resolved.

### Edge

Needed Babel to transpile unsupported ES features.

### Internet Explorer

Same as above, but only with the deprecated `@babel/polyfill`, not the new method of:
```js
import "core-js/stable";
import "regenerator-runtime/runtime";
```
…which made it run into an unhandled exception in a script adding support for WeakMap.

## Filter panel cannot be viewed in full unless you scroll to the bottom of the listings shown

- This is the result of `position: sticky`.
  - Was trying to offer the same effect as on Mobile where the Filters Panel stays with you as you scroll the listings.
- Can revert to `position: static` to keep it in one place, allowing people to scroll past it.
  - Could also make this dynamic. Two options:
    - Make the Filters Panel sticky only if the height of the Results Panel is some amount taller than it e.g. 1.5x, 2x, etc.
    - Make the Filters Panel sticky only if the browser window is tall enough to contain the entire thing at once.
- Can adapt the Mobile treatment of the Filters Panel to Desktop (anchored to top).
- Can maybe combine sticky + scrolling and make Filters Panel individually scrollable.

## Checkbox showing up on top of pages

- Removed the checkbox on CI, but there is still additional whitespace being added by Boston.gov. public.css sets `.mn { padding-top: 65px; }` but then adds `.page { padding-top: 6.6em }`. So for the Metrolist stylesheet only we’re resetting to what it was before via `.page { padding-top: 65px; }`.

## Not Loading on IE11

After investigating the issue I have discovered the following:

- The Search page was working fine on IE 11.418.18362.0, but after upgrading to IE 11.836.18362.0 (via Windows Update), the page broke in the way David Wilcox describes.
- Ruled out, but provided for posterity: The CORS policy on the Acquia server doesn’t allow us to fetch the Developments API from a domain other than `boston.gov`, therefore `d8-dev2.boston.gov` et al. fail. This won’t be an issue on production when `boston.gov` is the domain from which all requests originate. Even the production site produces this same error.
- When disabling IE’s browser security to circumvent the CORS issue, the React app still doesn’t load due to an IE-specific JavaScript error in `fleetcomponents.gk78kqoc.js`. The error states:

> SCRIPT28: Out of stack space
> fleetcomponents.gk78kqoc.js (88,44)
> SCRIPT2343: Stack overflow at line: 88

- If the `fleet-components.patterns` section is commented out of `bos_theme.libraries.yml` in Drupal, then the app loads successfully. However this would disable the Fleet JavaScript across the entire site and as such is not a viable permanent fix.
- https://registry.boston.gov/birth also loads `fleetcomponents.gk78kqoc.js`, but does not produce the same error. Comparing the contents of this file with the file of the same name being loaded on `/metrolist/search` shows that they are identical.
- The error does not occur on `/metrolist/ami-estimator`
- `fleetcomponents.gk78kqoc.js` is outside of my purview, so it is not feasible for me to investigate/apply a direct fix of the code on my own. Would be something for the larger DoIT team to handle should they decide to do so.
- A temporary workaround would be to exclude `fleetcomponents.gk78kqoc.js` from the Metrolist Search page only and/or for IE 11 only.

## Shrink AMI Estimator

Existing sizing (1024x768 viewport):

- Heading
  - ML: 33.75px
  - Birth: 38px
- Step X of Y
  - ML: 18px (inherited, html)
  - Birth: 16px (inherited, default)
- Propmt Question
  - ML: 27px
  - Birth: 24px
- Image
  - ML Family: 255x255 including built-in padding (intrinsic: 150x150)
  - Birth Person: 100x100 excluding built-in padding
  - Birth 2x People: 140x100 excluding built-in padding
- 

## Address Design Feedback

### Sebastian

[notes on Metrolist.pdf]

- AMI Estimator
  - Remove border [on Yearly/Monthly toggle] - Removed the gray border from the blue “selected” state. If you’re saying we should remove the entire border then please specify (though I think that would make it harder to make out the buttons).
- Un-round corners
  - The rounded corners are not part of the button but rather the focus outline. In Chrome 83 (released a few weeks ago) the focus outline changed from a drop-shadow to a rounded border. Unfortunately in Chrome there is no way to modify the border radius without also changing the outline’s overall appearance. Notably when I do change to the squared style, then we also lose the outline around the AMI filter’s range thumbs (see attached). The range thumbs still turn red when highlighted but from an accessibility standpoint we can’t rely on color alone to convey information. But regardless, if the outline style is to be modified at all it should probably be modified for all of Boston.gov and not just Metrolist so we have a consistent experience. 

### André

[Google Doc](https://docs.google.com/document/d/1y7RMUiQkozv4pJfhBMspk1UtrSmO75XndrvwN2HRbr0/edit)

- When user clicks on one of the drawers it adds a white space/box select, but only at the top
  - This is also due to the focus outline. Can remove but not without the same issues described above.

## [Metrolist fix for Google Translate](https://github.com/CityOfBoston/boston.gov-d8/pull/1477)

Fixes #1446

Verify on CI:
- [Search](https://translate.google.com/translate?sl=auto&tl=ja&u=https%3A%2F%2Fd8-ci.boston.gov%2Fmetrolist%2Fsearch)
- [AMI Estimator](https://translate.google.com/translate?sl=auto&tl=ja&u=https%3A%2F%2Fd8-ci.boston.gov%2Fmetrolist%2Fami-estimator)

Note that the homes do not load because the API call is blocked by CORS. If Chrome is run with web security disabled then they do load, which should emulate what we’d see on production.

### Bug
While Metrolist loads under the offsite version of Google Translate, the React views (Search and AMI Estimator) do not populate.

### Background
- React Router matches on the current URL (`window.location` or `document.location`).
- The Google Translate widget loads the entire page into an `iframe`.
- Under normal circumstances, the React page loading inside of an `iframe` would not break anything, since `iframe`s are self-contained. The `location` would still be e.g. `https://www.boston.gov/metrolist/search` even if included on another domain. However, Google needs to modify the content on the page in order to translate it, and it isn’t possible to modify the contents of an `iframe` from the parent page (unless they talk to each other using `postMessage`). Therefore, Google merely scrapes the content of the included page and dynamically inserts it into an `iframe` that it controls.
- Because of the above process, the `location` under Google Translate is not `www.boston.gov` but rather `translate.googleusercontent.com`. The path of the page becomes `/translate_c`, which throws off React Router matching that expects `/metrolist`.
  > index.bundle.js?v=2.x:2 Warning: You are attempting to use a basename on a page whose URL path does not begin with the basename. Expected path "/translate_c?depth=1&pto=aue&rurl=translate.google.com&sl=auto&sp=nmt4&tl=ja&u=https://www.boston.gov/metrolist/ami-estimator&usg=ALkJrhjYWXizTPU7YYBqcKUYUV0LgW-l5g" to begin with "/metrolist".
- Google Translate also adds a `base` tag to the page’s `head` set to the original URL (e.g. `<base href="https://www.boston.gov/metrolist/ami-estimator" />`. This is to make sure relative links won’t break from being on a different domain. Ironically this breaks navigation for Single-Page Apps, which use the HTML5 History API rather than doing a real server request. History API cannot update `location`s across domains:
  > Uncaught DOMException: Failed to execute 'pushState' on 'History': A history state object with URL 'https://www.boston.gov/metrolist/ami-estimator/household-income' cannot be created in a document with origin 'https://translate.googleusercontent.com' and URL 'https://translate.googleusercontent.com/translate_c?depth=1&pto=aue&rurl=translate.google.com&sl=auto&sp=nmt4&tl=ja&u=https://www.boston.gov/metrolist/ami-estimator&usg=ALkJrhjWcZACKPBWvA4U6iyZm2NCx47XBw'.
- Clicking the link on `/metrolist/ami-estimator/result` to `/metrolist/search` from within Google Translate is not possible since Google puts an `X-Frame-Options` security header on the `iframe`.

### Solution
- The top-level basename of `/metrolist` was removed and the routes were updated from `/`, `/search`, and `/ami-estimator` to `/metrolist/`, `/metrolist/search`, and `/metrolist/ami-estimator` respectively. This removed the basename mismatch console warning, but it did not fix the routing.
- We detect whether we are inside a Google Translate `iframe`, i.e. if the current domain is `translate.googleusercontent.com`—which should match 100% of the time, but in case that were to change we also check for `translate.google.com` or the path `/translate_c`—and if there is a query string present. If both conditions are met, we scan for a query string parameter pointing to `/metrolist/*`, then extract the path from the first match. Google Translate re-hosts the page content by scraping whatever is specified in the `u` parameter (“u” for “URL” most likely), e.g. `u=https://www.boston.gov/metrolist/search`. Given that parameter is found, we can extract `/metrolist/search` and then manually override the React Router location to think it is on `/metrolist/search` even if it is actually on `/translate_c`.
- Additionally, we store a reference to the Google URL in localStorage (`metrolistGoogleTranslateIframeUrl`) for later use.
- On forward/back navigation between AMI Estimator subroutes, we temporarily change the `base` from `https://www.boston.gov/metrolist/ami-estimator` (or equivalent dev environment) to `https://translate.googleusercontent.com/metrolist/ami-estimator`. Even though the latter URL does not exist, it satisfies the necessary security conditions for navigation by keeping us on the same domain. Then, after navigating, the `base` is immediately changed back to `boston.gov` so links and assets do not break.
- Finally, the link on `/metrolist/ami-estimator/result` to `/metrolist/search` is swapped out with a new Google Translate URL. If left alone, then the untranslated Search page would load inside the `iframe`. So we read `localStorage.metrolistGoogleTranslateIframeUrl` and replace the `u` parameter with the equivalent `/metrolist/search` URL for whatever domain it’s on. This URL has to be read from localStorage because if we try to read `window.parent.location.href` it will be blocked for security reasons:
  > Uncaught DOMException: Blocked a frame with origin "https://translate.googleusercontent.com" from accessing a cross-origin frame.
  It also has to be loaded in a new tab/window with `<a target="_blank"></a>` because otherwise we get another security error:
  > Refused to display 'https://translate.google.com/translate?depth=1&pto=aue&rurl=translate.google.com&sl=auto&sp=nmt4&tl=ja&u=https://www.boston.gov/metrolist/search' in a frame because it set 'X-Frame-Options' to 'deny'. 

### Caveats
- If Google Translate changes the way their code works, this could break.
- Although this fix is verifiable on CI as far as translation goes, until the appropriate CORS headers are added to Acquia, the parts of the app that rely on API data will not resolve, so it will still appear broken. Although this also has to do with cross-origin restrictions, it is completely unrelated to the Translate issue, so it is safe to ignore. But to work around this and verify that the site is indeed 100% working, you can run Chrome without security enabled. Download Chrome Canary and run this command (macOS, but you can search for your platform equivalent): `open -n -a Google\ Chrome\ Canary --args --disable-web-security --user-data-dir=/tmp/chrome --disable-site-isolation-trials --allow-running-insecure-content`.

## Clear Filters Show/Hide
- On initial page load, should be hidden if localStorage.filters doesn’t exist or is identical to the default filters
  - Presents a chicken-and-egg problem where we don’t actually know the upperBound of the Rental Price filter until an API response comes back
  - Default Filters are defined before any API work has been done