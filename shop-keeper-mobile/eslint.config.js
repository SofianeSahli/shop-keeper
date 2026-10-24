// https://docs.expo.dev/guides/using-eslint/
const { defineConfig } = require('eslint/config');
const expoConfig = require("eslint-config-expo/flat");

const CONSTITUTION = 'Constitution II: styles live in tailwind.config.js and .css files; use token classes.';

module.exports = defineConfig([
  expoConfig,
  {
    ignores: ["**/node_modules/**", "**/dist/**", "**/build/**", "**/coverage/**", "**/*.min.js"],
  },
  {
    files: ["**/*.tsx"],
    rules: {
      "no-restricted-syntax": [
        "error",
        {
          selector: "Literal[value=/#[0-9a-fA-F]{3,8}\\b/]",
          message: `No hex colors in .tsx files. ${CONSTITUTION}`,
        },
        {
          selector: "TemplateElement[value.raw=/#[0-9a-fA-F]{3,8}\\b/]",
          message: `No hex colors in .tsx files. ${CONSTITUTION}`,
        },
        {
          selector: "JSXAttribute[name.name='style']",
          message: `No inline style props. ${CONSTITUTION}`,
        },
        {
          selector: "ImportSpecifier[imported.name='StyleSheet']",
          message: `No StyleSheet. ${CONSTITUTION}`,
        },
      ],
    },
  },
]);
