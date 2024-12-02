const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cleanCSS = require('gulp-clean-css');
const sourcemaps = require('gulp-sourcemaps');
const concat = require('gulp-concat');

const paths = {
    scss: './scss/**/*.scss', // All SCSS files
    css: './css'             // Output folder
};

// Concatenate and compile SCSS
function compileScss() {
    return gulp.src(paths.scss)
        .pipe(sourcemaps.init())
        .pipe(concat('style.scss'))            // Concatenate all SCSS into one file
        .pipe(sass().on('error', sass.logError))  // Compile SCSS to CSS
        .pipe(postcss([autoprefixer({ overrideBrowserslist: ['last 2 versions'] })]))
        .pipe(cleanCSS({ compatibility: 'ie8' })) // Minify CSS
        .pipe(sourcemaps.write('./'))
        .pipe(gulp.dest(paths.css));
}

// Watch files for changes
function watchFiles() {
    gulp.watch(paths.scss, compileScss);
}

// Define tasks
const build = gulp.series(compileScss);
const watch = gulp.parallel(watchFiles);

exports.compileScss = compileScss;
exports.watch = watch;
exports.default = build;
