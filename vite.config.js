import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // Site público: identidade da Associação Ceará Design (cearadesign.org.br):
                // títulos em Roboto (condensada, como o logo) e texto em Source Sans.
                bunny('Roboto Condensed', {
                    weights: [500, 700],
                }),
                bunny('Source Sans 3', {
                    weights: [400, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
