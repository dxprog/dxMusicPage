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
                    'js/dev/*.js'
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
                    [ 'babelify', { 'stage': 0 }]
                ],
                require: [
                    './node_modules/jquery/dist/jquery.js:jquery',
                    './node_modules/fiber/src/fiber.js:fiber'
                ]
            },
            dist: {
                src: [ './js/dev/dxmp.js' ],
                dest: './js/dxmp.js'
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