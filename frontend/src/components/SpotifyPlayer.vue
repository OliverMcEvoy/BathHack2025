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
                    @click="playRecentTrack(recentTrack)">
                    <img :src="recentTrack.album.images[2].url" class="recent-art" />
                    <div class="recent-info">
                        <div class="recent-title">{{ recentTrack.name }}</div>
                        <div class="recent-artist">{{ recentTrack.artists[0].name }}</div>
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
                    <button class="search-button" @click="fetchTrack">
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
                            <img :src="track.album.images[0].url" :alt="track.name" class="album-art" />
                        </div>

                        <!-- Track Title and Artist -->
                        <h1 class="track-title">{{ track.name }}</h1>
                        <p class="artist">{{track.artists.map(a => a.name).join(', ')}}</p>

                        <!-- Valence and Mood -->
                        <p class="valence-info">Valence: {{ valence.toFixed(2) }}</p>
                        <p class="mood-info">Mood: {{ selectedMood }}</p>

                        <!-- Audio Controls -->
                        <div class="audio-controls">
                            <button class="control-button prev" @click="prevTrack">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button class="control-button play" @click="togglePlay"
                                :style="{ background: `linear-gradient(135deg, ${moods[selectedMood][0]}, ${moods[selectedMood][1]})` }">
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

                        <!-- Audio Loading and Error States -->
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
            trackId: '77oU2rjC5XbjQfNe3bD6so',
            track: null,
            valence: 0.5, // Default valence
            audioElement: null,
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
            return {
                background: `linear-gradient(135deg, ${this.moods[this.selectedMood][0]}, ${this.moods[this.selectedMood][1]})`
            };
        },
        currentTimeFormatted() {
            return this.formatTime(this.currentTime);
        },
        durationFormatted() {
            return this.formatTime(this.audioElement?.duration || 0);
        },
        moodColor() {
            return this.moods[this.selectedMood][0]; // Use the first color of the mood gradient
        },
        darkerMoodColor() {
            return this.moods[this.selectedMood][1]; // Use the second color of the mood gradient for a darker shade
        }
    },
    methods: {
        async getOAuthToken(callback) {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/token');
                if (response.data.token) {
                    callback(response.data.token);
                } else {
                    throw new Error(response.data.error || 'Invalid token received');
                }
            } catch (error) {
                console.error('Error fetching Spotify token:', error);
                this.audioError = 'Failed to fetch Spotify token. Ensure you are logged in and authorized.';
            }
        },
        async fetchSpotifyData() {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/proxy', {
                    params: { type: 'dealer', type: 'spclient' },
                });
                console.log('Spotify API response:', response.data);
            } catch (error) {
                console.error('Error fetching Spotify data:', error);
            }
        },
        async authenticate() {
            try {
                const response = await axios.get('http://127.0.0.1:8000/spotify/authorize');
                if (response.data.authUrl) {
                    window.location.href = response.data.authUrl;
                }
            } catch (error) {
                console.error('Authentication failed:', error);
                this.audioError = 'Failed to authenticate with Spotify';
            }
        },
        formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = Math.floor(seconds % 60);
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        },
        async fetchTrack() {
            try {
                this.audioError = null;
                const response = await axios.get('http://127.0.0.1:8000/spotify/track', {
                    params: { track_id: this.trackId }
                });
                this.track = response.data;
                this.addToRecentTracks(this.track);
                await this.setupAudio();

                // Fetch valence from the backend
                const valenceResponse = await axios.get('http://127.0.0.1:8000/external-api');
                this.valence = valenceResponse.data.valence || 0.5;

                // Update mood based on valence
                this.updateMood();
            } catch (error) {
                console.error('Error fetching track or valence:', error);
                this.audioError = 'Failed to load track information';
            }
        },
        updateMood() {
            if (this.valence < 0.3) {
                this.selectedMood = 'Melancholy';
            } else if (this.valence < 0.5) {
                this.selectedMood = 'Calm';
            } else if (this.valence < 0.7) {
                this.selectedMood = 'Chill';
            } else if (this.valence < 0.9) {
                this.selectedMood = 'Happy';
            } else {
                this.selectedMood = 'Energetic';
            }
        },
        addToRecentTracks(track) {
            if (!this.recentTracks.some(t => t.id === track.id)) {
                this.recentTracks.unshift(track);
                if (this.recentTracks.length > 5) {
                    this.recentTracks.pop();
                }
            }
        },
        playRecentTrack(track) {
            this.trackId = track.id;
            this.fetchTrack();
        },
        async initializeSpotifyPlayer() {
            const script = document.createElement('script');
            script.src = 'https://sdk.scdn.co/spotify-player.js';
            script.async = true;
            document.body.appendChild(script);

            return new Promise((resolve) => {
                window.onSpotifyWebPlaybackSDKReady = () => {
                    this.player = new window.Spotify.Player({
                        name: 'SoundScape Web Player',
                        getOAuthToken: cb => {
                            this.getOAuthToken(cb);
                        }
                    });

                    this.player.connect().then(success => {
                        if (success) {
                            console.log('Successfully connected to Spotify');
                        }
                    });

                    this.player.addListener('ready', ({ device_id }) => {
                        this.deviceId = device_id;
                        resolve();
                    });

                    this.player.addListener('player_state_changed', state => {
                        if (state) {
                            this.isPlaying = !state.paused;
                            this.currentTime = state.position;
                            this.updateProgress();
                        }
                    });

                    this.player.addListener('not_ready', ({ device_id }) => {
                        console.log('Device ID has gone offline', device_id);
                    });
                };
            });
        },
        async setupAudio() {
            try {
                this.audioLoading = true;
                this.audioError = null;

                // Initialize Spotify Web Playback SDK if not already done
                if (!this.player) {
                    await this.initializeSpotifyPlayer();
                }

                const response = await axios.get(`http://127.0.0.1:8000/spotify/audio?track_id=${this.track.id}`);

                if (response.data.success) {
                    // Store the device ID for future use
                    this.deviceId = response.data.deviceId;

                    // Update valence from the response
                    this.valence = response.data.valence || 0.5;

                    // Update mood based on the new valence
                    this.updateMood();

                    // Start playback
                    await this.player.resume();
                    this.isPlaying = true;
                } else {
                    throw new Error(response.data.error || 'Failed to initialize playback');
                }
            } catch (error) {
                console.error('Audio setup error:', error);
                this.audioError = 'Error setting up audio. Make sure you\'re logged into Spotify Premium and have an active device';
                this.isPlaying = false;
            } finally {
                this.audioLoading = false;
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
            } catch (e) {
                console.error('Playback error:', e);
                this.audioError = 'Playback failed';
                this.isPlaying = false;
            }
        },
        prevTrack() {
            if (this.audioElement) {
                this.audioElement.currentTime = 0;
            }
        },
        nextTrack() {
            if (this.recentTracks.length > 0) {
                this.playRecentTrack(this.recentTracks[0]);
            }
        },
        updateProgress() {
            if (this.player && this.track) {
                this.player.getCurrentState().then(state => {
                    if (state) {
                        this.currentTime = state.position / 1000;
                        this.progressPercentage = (state.position / state.duration) * 100;
                    }
                });
            }
        },
        seekAudio(event) {
            if (this.player && this.track) {
                const rect = event.currentTarget.getBoundingClientRect();
                const seekPosition = (event.clientX - rect.left) / rect.width;
                const seekTime = seekPosition * this.audioElement.duration;
                this.player.seek(seekTime * 1000);
            }
        }
    },
    mounted() {
        this.initializeSpotifyPlayer();
        setInterval(this.updateProgress, 1000); // Update progress every second
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
</style>