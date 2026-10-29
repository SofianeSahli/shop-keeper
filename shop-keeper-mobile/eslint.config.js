const { defineConfig } = require('eslint/config');
const expoConfig = require("eslint-config-expo/flat");

const CONSTITUTION = 'Constitution II: styles live in tailwind.config.js and .css files; use token classes.';
const COLOR_VALUE_MESSAGE = `No color values in source files: add a role in src/theme/tokens.css. ${CONSTITUTION}`;

const NO_COLOR_VALUES = [
  { selector: "Literal[value=/#[0-9a-fA-F]{3,8}\\b/]", message: COLOR_VALUE_MESSAGE },
  { selector: "TemplateElement[value.raw=/#[0-9a-fA-F]{3,8}\\b/]", message: COLOR_VALUE_MESSAGE },
  { selector: "Literal[value=/rgba?[(]/]", message: COLOR_VALUE_MESSAGE },
  { selector: "TemplateElement[value.raw=/rgba?[(]/]", message: COLOR_VALUE_MESSAGE },
];

module.exports = defineConfig([
  expoConfig,
  {
    ignores: ["**/node_modules/**", "**/dist/**", "**/build/**", "**/coverage/**", "**/*.min.js"],
  },
  {
    files: ["src/**/*.ts"],
    ignores: ["**/*.test.ts"],
    rules: {
      "no-restricted-syntax": ["error", ...NO_COLOR_VALUES],
    },
  },
  {
    files: ["**/*.tsx"],
    rules: {
      "no-restricted-syntax": [
        "error",
        ...NO_COLOR_VALUES,
        {
          selector: "JSXAttribute[name.name='style'][value.type='JSXExpressionContainer']",
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
