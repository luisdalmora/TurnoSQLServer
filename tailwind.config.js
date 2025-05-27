// tailwind.config.js
module.exports = {
  // darkMode: 'class', // REMOVIDO
  content: ["./*.{php,html,js}", "./src/**/*.{html,js,php}"],
  theme: {
    extend: {
      fontFamily: {
        poppins: ["Poppins", "sans-serif"],
        "roboto-mono": ['"Roboto Mono"', "monospace"],
      },
    },
  },
  plugins: [require("@tailwindcss/forms")],
};
