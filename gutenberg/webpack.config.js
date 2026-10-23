const config = require('@wordpress/scripts/config/webpack.config');

// All editor fonts/images are emitted from imports. Keeping old files here
// silently ships retired assets after an upgrade of the icon package.
module.exports = { ...config, output: { ...config.output, clean: true } };
