var gulp        = require('gulp'),
	git         = require('gulp-git'),
	debug       = require("gulp-debug"),
	bump        = require('gulp-bump'),
	filter      = require('gulp-filter'),
	tag_version = require('gulp-tag-version');

gulp.task('bump', function () {

	return gulp.src(['./package.json', './composer.json'])
		.pipe(bump({type: 'patch', indent: 4}))
		.pipe(gulp.dest('./'))
		.pipe(git.commit('bumps package version'))
		.pipe(filter('package.json'))
		.pipe(tag_version());

});

