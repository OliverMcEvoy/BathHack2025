<template>
    <div class="spotify-desktop" :style="backgroundStyle">
        <!-- Sidebar with Mood Selector -->
        <div class="sidebar">
            <div class="logo">🎵 SoundScape</div>
            <div class="mood-selector-container">
                <label class="mood-label">MOOD</label>
                <select v-model="selectedMood" class="mood-selector">
                    <option v-for="(color, mood) in moods" :key="mood" :value="mood">
                        {{ mood }}
                    </option>
                </select>
            </div>
            <div class="recent-tracks" v-if="recentTracks.length">
                <h3>RECENT TRACKS</h3>
                <div v-for="(recentTrack, index) in recentTracks" :key="index" class="recent-track"
                    :class="{ loading: recentTrack.loading }" @click="playRecentTrack(recentTrack)">
                    <div class="recent-art-wrapper">
                        <img v-if="recentTrack.album?.images?.length >= 3" :src="recentTrack.album.images[2].url"
                            class="recent-art" />
                        <div v-else class="recent-art-placeholder">
                            <i class="fas fa-music"></i>
                        </div>
                    </div>
                    <div class="recent-info">
                        <div class="recent-title">{{ recentTrack.name || 'Unknown Track' }}</div>
                        <div class="recent-artist">
                            {{ recentTrack.artists?.[0]?.name || 'Unknown Artist' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Search Bar -->
            <div class="search-container">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input v-model="trackId" class="search-input" placeholder="Enter Spotify Track ID or Search..." />
                    <button class="search-button" @click="fetchTrack" :disabled="audioLoading">
                        {{ track ? 'Update' : 'Search' }}
                    </button>
                </div>
            </div>

            <!-- Now Playing Section -->
            <div v-if="track" class="now-playing centered">
                <!-- Track Info Column -->
                <div class="track-column">
                    <div class="track-info">
                        <!-- Album Art -->
                        <div class="album-art-container" :class="{ rotating: isPlaying }">
                            <img v-if="track.album?.images?.length" :src="track.album.images[0].url" :alt="track.name"
                                class="album-art" />
                            <div v-else class="album-art-placeholder">
                                <i class="fas fa-music"></i>
                            </div>
                        </div>

                        <!-- Track Title and Artist -->
                        <h1 class="track-title">{{ track.name }}</h1>
                        <p class="artist">{{track.artists?.map(a => a.name).join(', ') || 'Unknown Artist'}}</p>

                        <!-- Valence and Mood -->
                        <p class="valence-info">Valence: {{ valence.toFixed(2) }}</p>
                        <p class="mood-info">Mood: {{ selectedMood }}</p>

                        <!-- Audio Controls -->
                        <div class="audio-controls">
                            <button class="control-button prev" @click="prevTrack">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button class="control-button play" @click="togglePlay"
                                :style="{ background: `linear-gradient(135deg, ${moods[selectedMood][0]}, ${moods[selectedMood][1]})` }"
                                :disabled="audioLoading">
                                <i :class="isPlaying ? 'fas fa-pause' : 'fas fa-play'"></i>
                            </button>
                            <button class="control-button next" @click="nextTrack">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>

                        <!-- Progress Bar -->
                        <div class="progress-container">
                            <div class="progress-bar" @click="seekAudio">
                                <div class="progress" :style="{ width: progressPercentage + '%' }"></div>
                            </div>
                            <div class="progress-time">
                                <span>{{ currentTimeFormatted }}</span>
                                <span>{{ durationFormatted }}</span>
                            </div>
                        </div>

                        <!-- Loading and Error States -->
                        <div v-if="audioLoading" class="audio-state">
                            <i class="fas fa-spinner fa-spin"></i> Loading audio...
                        </div>
                        <div v-if="audioError" class="audio-state error">
                            <i class="fas fa-exclamation-triangle"></i> {{ audioError }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="empty-state">
                <div class="empty-content">
                    <i class="fas fa-music empty-icon"></i>
                    <h2>No Track Selected</h2>
                    <p>Enter a Spotify Track ID or search for music to begin</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

export default {
    data() {
        return {
            trackId: '',
            track: null,
            valence: 0.5,
            player: null,
            deviceId: null,
            isPlaying: false,
            progressPercentage: 0,
            currentTime: 0,
            selectedMood: 'Calm',
            recentTracks: [],
            audioLoading: false,
            audioError: null,
            moods: {
                'Calm': ['#B8E8FC', '#FDF6EC'],
                'Happy': ['#FFEEAF', '#FFB5B5'],
                'Energetic': ['#FFBED8', '#CDE990'],
                'Melancholy': ['#D3CEDF', '#F7DBF0'],
                'Focus': ['#E0F9B5', '#A5DEE5'],
                'Chill': ['#D4F4FA', '#F5E6CA']
            }
        };
    },
    computed: {
        backgroundStyle() {
            return this.track ? {
                background: `linear-gradient(135deg, ${this.moods[this.selectedMood][0]}, ${this.moods[this.selectedMood][1]})`
            } : {};
        },
        currentTimeFormatted() {
            return this.formatTime(this.currentTime);
        },
        durationFormatted() {
            return this.formatTime(this.track?.duration_ms / 1000 || 0);
        }
    },
    methods: {
        async getOAuthToken(callback) {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/token');
                if (response.data.token) {
                    callback(response.data.token);
                } else {
                    throw new Error(response.data.error || 'Invalid token');
                }
            } catch (error) {
                console.error('Token error:', error);
                this.audioError = 'Failed to get Spotify token';
            }
        },

        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },

        async fetchTrack() {
            try {
                this.audioError = null;
                this.audioLoading = true;

                // Get recommendation
                const recResponse = await axios.get('http://127.0.0.1:8000/spotify/rec');
                if (!recResponse.data?.recommendation) {
                    throw new Error('No track recommendation received');
                }
                this.trackId = recResponse.data.recommendation;

                // Get track details
                const trackResponse = await axios.get('http://127.0.0.1:8000/spotify/track', {
                    params: { track_id: this.trackId }
                });

                if (!trackResponse.data?.id || !trackResponse.data?.artists) {
                    throw new Error('Invalid track data structure');
                }

                // Process track data
                this.track = {
                    ...trackResponse.data,
                    album: {
                        ...trackResponse.data.album,
                        images: trackResponse.data.album?.images || []
                    },
                    artists: trackResponse.data.artists || []
                };

                this.valence = trackResponse.data.valence ?? 0.5;
                this.updateMood();
                this.addToRecentTracks(this.track);
                await this.setupAudio();

            } catch (error) {
                console.error('Fetch error:', error);
                this.audioError = error.message || 'Failed to load track';
            } finally {
                this.audioLoading = false;
            }
        },

        updateMood() {
            if (this.valence < 0.3) this.selectedMood = 'Melancholy';
            else if (this.valence < 0.5) this.selectedMood = 'Calm';
            else if (this.valence < 0.7) this.selectedMood = 'Chill';
            else if (this.valence < 0.9) this.selectedMood = 'Happy';
            else this.selectedMood = 'Energetic';
        },

        addToRecentTracks(track) {
            if (track?.id && track?.name) {
                const exists = this.recentTracks.some(t => t.id === track.id);
                if (!exists) {
                    this.recentTracks.unshift({
                        id: track.id,
                        name: track.name,
                        artists: track.artists || [],
                        album: {
                            images: track.album?.images || []
                        }
                    });
                    if (this.recentTracks.length > 5) this.recentTracks.pop();
                }
            }
        },

        async playRecentTrack(track) {
            if (track.loading) return;
            try {
                track.loading = true;
                this.trackId = track.id;
                await this.fetchTrack();
            } finally {
                track.loading = false;
            }
        },

        async initializeSpotifyPlayer() {
            if (this.player) return;

            const script = document.createElement('script');
            script.src = 'https://sdk.scdn.co/spotify-player.js';
            script.async = true;
            document.body.appendChild(script);

            return new Promise((resolve) => {
                window.onSpotifyWebPlaybackSDKReady = () => {
                    this.player = new window.Spotify.Player({
                        name: 'SoundScape Player',
                        getOAuthToken: cb => this.getOAuthToken(cb),
                        volume: 0.5
                    });

                    // Error handling
                    this.player.addListener('initialization_error', ({ message }) => {
                        this.audioError = `Player error: ${message}`;
                    });
                    this.player.addListener('authentication_error', ({ message }) => {
                        this.audioError = 'Auth failed - please refresh';
                    });
                    this.player.addListener('account_error', ({ message }) => {
                        this.audioError = 'Premium account required';
                    });

                    this.player.connect().then(success => {
                        if (success) {
                            console.log('Spotify player connected');
                            resolve();
                        }
                    });

                    this.player.addListener('ready', ({ device_id }) => {
                        this.deviceId = device_id;
                    });

                    this.player.addListener('player_state_changed', state => {
                        if (state) {
                            this.isPlaying = !state.paused;
                            this.currentTime = state.position / 1000;
                            this.progressPercentage = (state.position / state.duration) * 100;
                        }
                    });
                };
            });
        },

        async setupAudio() {
            try {
                if (!this.player) {
                    await this.initializeSpotifyPlayer();
                }

                await this.player._options.getOAuthToken(async accessToken => {
                    const response = await fetch(`https://api.spotify.com/v1/me/player/play?device_id=${this.deviceId}`, {
                        method: 'PUT',
                        headers: {
                            'Authorization': `Bearer ${accessToken}`,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            uris: [`spotify:track:${this.trackId}`]
                        })
                    });

                    if (!response.ok) throw new Error('Playback start failed');
                    this.isPlaying = true;
                });

            } catch (error) {
                console.error('Playback error:', error);
                this.audioError = 'Playback failed - check Premium status';
                this.isPlaying = false;
            }
        },

        async togglePlay() {
            if (!this.track || this.audioLoading) return;

            try {
                if (this.isPlaying) {
                    await this.player.pause();
                } else {
                    await this.player.resume();
                }
                this.isPlaying = !this.isPlaying;
            } catch (error) {
                console.error('Playback toggle error:', error);
                this.audioError = 'Playback control failed';
            }
        },

        prevTrack() {
            this.player.previousTrack();
        },

        nextTrack() {
            this.player.nextTrack();
        },

        seekAudio(event) {
            if (this.player && this.track) {
                const rect = event.currentTarget.getBoundingClientRect();
                const seekPosition = (event.clientX - rect.left) / rect.width;
                const seekTime = seekPosition * (this.track.duration_ms / 1000);
                this.player.seek(seekTime * 1000);
            }
        }
    },
    mounted() {
        this.initializeSpotifyPlayer();
        setInterval(() => {
            if (this.isPlaying) {
                this.currentTime += 1;
                this.progressPercentage = (this.currentTime / (this.track?.duration_ms / 1000)) * 100;
            }
        }, 1000);
    },
    beforeUnmount() {
        if (this.player) {
            this.player.disconnect();
        }
    }
};
</script>

<style>
/* Base Layout for 16:9 Desktop */
.spotify-desktop {
    display: grid;
    grid-template-columns: 250px 1fr;
    height: 100%;
    width: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    transition: background 0.5s ease;
}

html,
body {
    margin: 0;
    padding: 0;
    height: 100%;
    overflow: hidden;
}

/* Sidebar Styles */
.sidebar {
    background: rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(15px);
    padding: 1.5rem 1rem;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.logo {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 2rem;
    color: #000;
    text-shadow: none;
}

.mood-selector-container {
    margin-bottom: 2rem;
}

.mood-label {
    display: block;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 0.5rem;
    color: #000;
}

.mood-selector {
    width: 100%;
    padding: 0.8rem 1rem;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    color: #000;
    font-size: 1rem;
    backdrop-filter: blur(5px);
    transition: all 0.3s ease;
}

.mood-selector:hover {
    background: rgba(255, 255, 255, 0.2);
}

.recent-tracks {
    margin-top: 1rem;
}

.recent-tracks h3 {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #000;
    margin-bottom: 1rem;
}

.recent-track {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.recent-track:hover {
    background: rgba(255, 255, 255, 0.1);
}

.recent-art {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    margin-right: 0.8rem;
}

.recent-info {
    flex: 1;
}

.recent-title {
    font-size: 0.9rem;
    color: #000;
    margin-bottom: 0.2rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.recent-artist {
    font-size: 0.8rem;
    color: #000;
}

/* Main Content Styles */
.main-content {
    display: flex;
    flex-direction: column;
    padding: 1.5rem;
    overflow-y: auto;
}

.search-container {
    margin-bottom: 2rem;
}

.search-box {
    display: flex;
    align-items: center;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 30px;
    padding: 0.5rem 1rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    max-width: 600px;
}

.search-icon {
    color: #000;
    margin-right: 0.5rem;
}

.search-input {
    flex: 1;
    border: none;
    background: transparent;
    padding: 0.8rem 0.5rem;
    font-size: 1rem;
    outline: none;
    color: #000;
}

.search-button {
    background: linear-gradient(135deg, #1DB954, #1ED760);
    border: none;
    color: white;
    padding: 0.6rem 1.5rem;
    border-radius: 20px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.search-button:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 10px rgba(29, 185, 84, 0.3);
}

/* Now Playing Section */
.now-playing {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 1.5rem;
    flex: 1;
}

.album-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 1rem;
}

.album-art-container {
    width: 150px;
    height: 150px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s ease;
    border: 4px solid rgba(255, 255, 255, 0.3);
}

.album-art {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.rotating {
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.album-meta {
    text-align: center;
}

.album-name {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: #000;
}

.album-year {
    font-size: 0.9rem;
    color: #000;
}

/* Track Column */
.track-column {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.track-info {
    max-width: 600px;
}

.track-title {
    text-align: center;
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: #000;
    line-height: 1.2;
}

.artist {
    text-align: center;
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    color: #000;
}

.valence-info,
.mood-info {
    font-size: 1rem;
    margin-top: 0.5rem;
    color: #000;
    text-align: center;
}

.audio-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    margin: 2rem 0;
}

.control-button {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
}

.control-button.prev,
.control-button.next {
    background: rgba(0, 0, 0, 0.1);
    color: #000;
}

.control-button.play {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #1DB954, #1ED760);
    box-shadow: 0 4px 15px rgba(29, 185, 84, 0.3);
    color: white;
}

.control-button.play:hover {
    transform: scale(1.1);
}

.progress-container {
    margin-top: 1rem;
}

.progress-bar {
    height: 6px;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 3px;
    cursor: pointer;
}

.progress {
    height: 100%;
    background: linear-gradient(90deg, #1DB954, #1ED760);
    border-radius: 3px;
    transition: width 0.1s linear;
}

/* Center the Now Playing Section */
.now-playing.centered {
    margin: auto;
    max-width: 1000px;
    /* Increased width */
    text-align: center;
    padding: 2rem;
    /* Added padding for better spacing */
    display: flex;
    /* Center content vertically and horizontally */
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* Progress Bar Time Display */
.progress-time {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    color: #000;
    margin-top: 0.5rem;
}

/* Empty State */
.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.empty-content {
    text-align: center;
    max-width: 400px;
}

.empty-icon {
    font-size: 4rem;
    color: #000;
    margin-bottom: 1rem;
}

.empty-content h2 {
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
    color: #000;
}

.empty-content p {
    color: #000;
    font-size: 1.1rem;
}

.audio-state {
    margin-top: 1rem;
    padding: 0.8rem;
    border-radius: 8px;
    text-align: center;
    font-size: 0.9rem;
    background: rgba(255, 255, 255, 0.2);
    color: #000;
}

.audio-state.error {
    background: rgba(255, 0, 0, 0.1);
    color: #000;
}

.audio-state i {
    margin-right: 0.5rem;
}

.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.auth-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #1DB954, #191414);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.auth-container {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.auth-button {
    background-color: #1DB954;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 24px;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 1rem auto 0;
}

.auth-button:hover {
    background-color: #1ed760;
    transform: scale(1.02);
}

.recent-art-placeholder,
.album-art-placeholder {
    background: #eee;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.recent-art-placeholder {
    width: 50px;
    height: 50px;
}

.album-art-placeholder {
    width: 200px;
    height: 200px;
}

.recent-art-placeholder .fa-music,
.album-art-placeholder .fa-music {
    color: #666;
}

.loading {
    opacity: 0.7;
    pointer-events: none;
}

.audio-state {
    margin-top: 1rem;
    padding: 0.5rem;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.9);
}

.audio-state.error {
    background: rgba(255, 0, 0, 0.1);
    color: #cc0000;
}
</style>