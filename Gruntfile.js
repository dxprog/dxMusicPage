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
                    'js/dev/*.js'
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
                'static/js/dev/lib/*.js',
                'static/js/dev/model/*.js',
                'static/js/dev/view/*.js',
                'static/js/dev/controls/*.js',
                'static/js/dev/*.js',
                'views/*.handlebars',
                'views/partials/*.handlebars',
                'static/scss/*.scss'
            ],
            tasks: ['handlebars', 'concat', 'babel']
        },
        babel: {
            dist: {
                files: {
                    'js/dxmp.js': 'js/dxmp.js'
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-handlebars');
    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-babel');

    grunt.registerTask('default', ['handlebars', 'sass', 'concat', 'babel']);

};