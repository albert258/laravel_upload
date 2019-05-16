let mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/assets/js/app.js', 'public/js').version();
mix.js('resources/assets/js/bootstrap.js', 'public/js').version();
mix.js('node_modules/jquery/dist/jquery.js', 'public/js').version();
mix.sass('resources/assets/sass/app.scss', 'public/css');
mix.sass('node_modules/bootstrap-sass/assets/stylesheets/_bootstrap.scss', 'public/css');
mix.copyDirectory('node_modules/admin-lte','public/admin-lte');
