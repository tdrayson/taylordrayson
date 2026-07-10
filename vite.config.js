import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import { existsSync, mkdirSync, readdirSync, copyFileSync } from "node:fs";
import path from "node:path";

/**
 * vite-plugin-static-copy only physically writes files to disk during
 * `vite build` (via its build-mode plugin). In dev/serve mode it instead
 * serves a virtual file map through Vite's own dev server middleware.
 * Herd serves `public/` directly and never proxies asset requests to
 * Vite's dev server, so `npm run dev` also needs the Twemoji SVGs
 * physically present on disk. This copies them into `public/twemoji/svg/`
 * once at startup (idempotent, skipped once the directory is populated) so
 * the same path serves the assets in both dev and build.
 */
function copyTwemojiSvgs() {
    return {
        name: "twemoji-svg-copy",
        buildStart() {
            const srcDir = path.resolve(process.cwd(), "node_modules/@twemoji/svg");
            const destDir = path.resolve(process.cwd(), "public/twemoji/svg");
            if (existsSync(destDir) && readdirSync(destDir).length > 0) {
                return;
            }
            mkdirSync(destDir, { recursive: true });
            for (const file of readdirSync(srcDir)) {
                if (file.endsWith(".svg")) {
                    copyFileSync(path.join(srcDir, file), path.join(destDir, file));
                }
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        copyTwemojiSvgs(),
    ],
    server: {
        // Allow Herd's *.test domain to reach the dev server (Vite 6 blocks
        // unknown hosts by default).
        allowedHosts: [".test"],
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
