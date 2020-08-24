// Turns off accessibility warnings during initial building phase
// via: https://github.com/airbnb/javascript/issues/2032#issuecomment-568934232
// const a11yOff = Object.keys(require('eslint-plugin-jsx-a11y').rules)
//   .reduce((acc, rule) => { acc[`jsx-a11y/${rule}`] = 'off'; return acc }, {})

module.exports = {
  "extends": [
    "hughx/react",
  ],
  "parser": "babel-eslint",
  "parserOptions": {
    "ecmaVersion": 2020,
  },
  // "rules": {
  //   ...a11yOff,
  // },
  "globals": {
    "globalThis": false, // means it is not writeable
  },
  "settings": {
    "import/resolver": {
      /*
        Match up with webpack.config.js alias
        so that eslint-plugin-import doesn’t complain.
        via: https://github.com/benmosher/eslint-plugin-import/issues/496#issuecomment-567769738
      */
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