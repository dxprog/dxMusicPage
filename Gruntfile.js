module.exports = function(grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        sass: {
            dist: {
                files: {
                    'static/scss/styles.css': 'static/scss/styles.scss',
                    'static/scss/mobile.css': 'static/scss/mobile.scss'
                }
            }
        },
        watch: {
            js: {
                files: [
                    'js/**/*.jsx'
                ],
                tasks: ['browserify']
            },
            configFiles: {
                files: [ 'Gruntfile.js' ],
                options: {
                    reload: true
                }
            }
        },
        browserify: {
            options: {
                transform: [
                    [ 'babelify', { stage: [ 'es2015', 'react' ] }]
                ],
                require: [
                    './node_modules/jquery/dist/jquery.js:jquery',
                    './node_modules/react/dist/react.js:react'
                ]
            },
            dist: {
                src: [ './js/index.jsx' ],
                dest: './dxmp.js'
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-handlebars');
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-browserify');

    grunt.registerTask('default', ['sass', 'browserify']);

};