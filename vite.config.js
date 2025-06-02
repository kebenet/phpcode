import { defineConfig } from 'vite';
import { nodeResolve } from '@rollup/plugin-node-resolve';

export default defineConfig({
  plugins: [
    nodeResolve({
      exportConditions: ['development', 'module', 'import', 'default'],
    }),
  ],
  build: {
    lib: {
      entry: 'src/index.js', // or your main Lit component file
      name: 'MyLitComponent',
      fileName: (format) => `my-lit-component.${format}.js`
    },
    rollupOptions: {
      // make sure to externalize deps that shouldn't be bundled
      // into your library
      external: ['lit'],
      output: {
        // Provide global variables to use in the UMD build
        // for externalized deps
        globals: {
          lit: 'Lit'
        }
      }
    }
  }
});
