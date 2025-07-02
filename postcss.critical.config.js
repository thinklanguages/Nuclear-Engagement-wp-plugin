/**
 * PostCSS Critical CSS Configuration
 */
module.exports = {
  plugins: [
    require('postcss-import')({
      path: ['assets/css']
    }),
    require('postcss-preset-env')({
      stage: 1
    }),
    require('postcss-nested'),
    require('postcss-custom-properties')({
      preserve: true
    }),
    require('autoprefixer'),
    require('cssnano')({
      preset: ['default', {
        discardComments: { removeAll: true }
      }]
    })
  ]
};