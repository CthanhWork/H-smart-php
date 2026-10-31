import type { Config } from "tailwindcss";

const config: Config = {
  darkMode: ["class"],
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        integralCF: ["IntegralCF", "sans-serif"],
        satoshi: ["Satoshi", "sans-serif"],
      },
      screens: {
        xs: "375px",
        sm: "640px",
        md: "768px",
        lg: "1024px",
        xl: "1280px",
      },
      width: {
        frame: "77.5rem",
      },
      maxWidth: {
        frame: "77.5rem",
      },
      colors: {
        background: "#FFFFFF",
        foreground: "#000000",
        tile: "#F0EEED",
        hero: "#F2F0F1",
        muted: "#F0F0F0",
        "muted-foreground": "rgb(0 0 0 / 0.60)",
        border: "rgb(0 0 0 / 0.10)",
        input: "#F0F0F0",
        ring: "#000000",
        destructive: "#FF3333",
        "accent-green": "#2f7049",
        "payment-border": "#D6DCE5",
      },
      borderRadius: {
        lg: "0.5rem",
        md: "calc(0.5rem - 2px)",
        sm: "calc(0.5rem - 4px)",
      },
    },
  },
  plugins: [require("tailwindcss-animate")],
};
export default config;
