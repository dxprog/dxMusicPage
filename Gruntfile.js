module.exports = function(grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        concat: {
            options: {
                separator: ';'
            },
            dist: {
                src: [
                    'js/lib/*.js',
                    'build/*.js'
                ],
                dest: 'js/dxmp.js'
            }
        },
        sass: {
            dist: {
                files: {
                    'static/scss/styles.css': 'static/scss/styles.scss',
                    'static/scss/mobile.css': 'static/scss/mobile.scss'
                }
            }
        },
        handlebars: {
            compile: {
                options: {
                    namespace: 'RB.Templates',
                    wrapped:true,
                    processName: function(filename) {
                        filename = filename.split('/');
                        filename = filename[filename.length - 1];
                        return filename.split('.')[0];
                    }
                },
                files: {
                    'static/js/templates.js': 'views/*.handlebars'
                }
            }
        },
        watch: {
            files: [
                'js/dev/*.js'
            ],
            tasks: ['handlebars', 'browserify', 'concat']
        },
        browserify: {
            options: {
              transform: [[ 'babelify', { 'stage': 0 }]]
            },
            dist: {
                files: [{
                    expand: true,
                    cwd: 'js/dev',
                    src: ['*.js'],
                    dest: 'build',
                    ext: '.js'
                }]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-handlebars');
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-browserify');

    grunt.registerTask('default', ['handlebars', 'sass', 'browserify', 'concat']);

};