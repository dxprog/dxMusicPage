import React from 'react';

export default <music-page><header id="pageHead">
    <h1>dxMusicPage</h1>
    <input type="text" id="search" />
    <span class="vlc control disabled" title="Play on External Device" id="vlc">External</span>
    <ul id="devices" class="dropDown"></ul>
</header>
<div id="main">
    <div class="gridBg"></div>
    <section id="lists" class="pane">
        <div id="option" class="header">
            <h2>Albums</h2>
            <ul id="options">
                <li>Albums</li>
                <li>Trending</li>
                <li>My Most Played</li>
                <li>Latest</li>
                <li>Shows</li>
                <li>Playlists</li>
                <li>Random Song Pool</li>
            </ul>
        </div>
        <ul id="mainList" class="list"></ul>
        <ol id="secondaryList" class="list"></ol>
    </section>
    <section id="content">
        <div id="nowPlaying">
            <h2>Now <span>Playing</span></h2>
        </div>
    </section>
    <section id="rightlist" class="pane">
        <div class="header">
            <h2>Current Playlist</h2>
            <span id="savePlaylist">Save Playlist</span>
            <span id="playlistLength"></span>
        </div>
        <ul id="playlist" class="list"></ul>
        <ul id="tagList" class="list"></ul>
    </section>
    <section id="uploads">
        <h2>Uploads</h2>
        <ul></ul>
        <span class="close">Close</span>
    </section>
    <section id="message"></section>
</div>
<footer id="controls">
    <span title="Play Song" class="play control" id="playPause">Play</span>
    <span title="Next Song" class="next control" id="next">Next</span>
    <span id="playIn" class="time">0:00</span>
    <div class="scrubBar">
        <span class="playHead control"></span>
    </div>
    <span id="playOut" class="time">0:00</span>
    <span title="Create Smart Playlist" class="control smart" id="smartPlaylist">Create Smart Playlist</span>
    <span title="Random" class="random control" id="random">Random</span>
    <span title="Random Settings" class="randomSettings control" id="randomSettings">Random Settings</span>
</footer>
<div class="fader"></div></music-page>;