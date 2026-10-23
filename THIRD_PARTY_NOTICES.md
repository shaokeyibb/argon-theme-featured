# Third-party notices

Runtime assets are bundled locally. Source/build tools and their dependency graph are recorded in package-lock.json.

| Package | Bundled version | License |
| --- | --- | --- |
| bootstrap | 5.3.8 | [MIT](licenses/bootstrap.txt) |
| @popperjs/core | 2.11.8 | [MIT](licenses/popperjs-core.txt) |
| @fortawesome/fontawesome-free | 7.3.1 | [(CC-BY-4.0 AND OFL-1.1 AND MIT)](licenses/fortawesome-fontawesome-free.txt) |
| photoswipe | 5.4.4 | [MIT](licenses/photoswipe.txt) |
| highlight.js | 11.12.0 | [BSD-3-Clause](licenses/highlight.js.txt) |
| katex | 0.19.0 | [MIT](licenses/katex.txt) |
| pangu | 10.4.1 | [MIT](licenses/pangu.txt) |
| qrcode | 1.5.4 | [MIT](licenses/qrcode.txt) |
| is-whitespace-character | 2.0.1 | [MIT](licenses/is-whitespace-character.txt) |
| Parsedown | 1.8.0 | [MIT](inc/lib/Parsedown-LICENSE.txt) |
| Argon banner design attribution | original motif | [MIT](licenses/argon-design-system.txt) |

Parsedown source: https://github.com/erusev/parsedown/tree/1.8.0. Only its class name is changed to `_Parsedown` to avoid plugin collisions.

Bootstrap 5 supplies the component CSS and JavaScript. theme-foundation.scss retains Argon colors, the banner motif and small aliases for saved markup; the bundled 2019 Argon Design System / Bootstrap 4 stylesheet and unused component styles are removed. The project and inherited Argon theme source remain under the root GPL license.
