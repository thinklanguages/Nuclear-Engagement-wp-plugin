/**
 * PostCSS Configuration - Nuclear Engagement Plugin
 * 
 * Modern CSS build pipeline configuration for the Nuclear Engagement plugin.
 * Optimizes CSS for production and provides development tools.
 */

module.exports = {
  plugins: [
    // Import resolution
    require('postcss-import')({
      path: ['assets/css']
    }),

    // Modern CSS features
    require('postcss-preset-env')({
      stage: 1,
      features: {
        'nesting-rules': true,
        'custom-media-queries': true,
        'media-query-ranges': true,
        'logical-properties-and-values': true,
        'color-functional-notation': true,
        'lab-function': true,
        'oklab-function': true,
        'color-mix': true,
        'cascade-layers': true
      }
    }),

    // CSS nesting support
    require('postcss-nested'),

    // Custom properties fallbacks
    require('postcss-custom-properties')({
      preserve: true,
      importFrom: [
        'assets/css/01-settings/_design-tokens.css'
      ]
    }),

    // Autoprefixer for vendor prefixes
    require('autoprefixer')({
      cascade: false,
      grid: 'autoplace'
    }),

    // Production optimizations
    ...(process.env.NODE_ENV === 'production' ? [
      // PurgeCSS to remove unused styles
      require('@fullhuman/postcss-purgecss')({
        content: [
          './templates/**/*.php',
          './admin/**/*.php',
          './inc/**/*.php',
          './assets/js/**/*.js'
        ],
        safelist: [
          // Preserve dynamic classes
          /^nuclen-/,
          /^ne-/,
          /^c-/,
          /^o-/,
          /^u-/,
          // WordPress admin classes
          /^wp-/,
          /^admin-/,
          // Dynamic state classes
          /active$/,
          /selected$/,
          /error$/,
          /success$/,
          /loading$/,
          /hidden$/,
          // Responsive classes
          /^sm:/,
          /^md:/,
          /^lg:/,
          // Theme classes
          /^ne-theme-/
        ],
        defaultExtractor: content => {
          // Extract class names from PHP and JS
          const matches = content.match(/[A-Za-z0-9-_:\/]+/g) || [];
          return matches;
        }
      }),

      // CSS optimization
      require('cssnano')({
        preset: ['default', {
          discardComments: {
            removeAll: true
          },
          normalizeWhitespace: true,
          mergeLonghand: true,
          mergeRules: true,
          minifyFontValues: true,
          minifyParams: true,
          minifySelectors: true,
          reduceIdents: false, // Keep custom property names
          zindex: false // Don't optimize z-index values
        }]
      }),

      // Critical CSS extraction
      require('postcss-critical-split')({
        output: 'critical',
        blockTag: '@critical'
      })
    ] : []),

    // Development tools
    ...(process.env.NODE_ENV === 'development' ? [
      // CSS validation
      require('stylelint')({
        configFile: '.stylelintrc.json'
      }),

      // Source maps
      require('postcss-reporter')({
        clearReportedMessages: true
      })
    ] : [])
  ]
};