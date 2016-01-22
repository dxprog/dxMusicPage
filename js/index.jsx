import React from 'react';
import PageTpl from './templates/page.jsx';

class HelloWorld extends React.Component {
    render() {
        return PageTpl;
    }
}

React.render(<HelloWorld />, document.body);